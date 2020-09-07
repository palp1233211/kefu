<?php

require_once '../vendor/autoload.php';
require_once __DIR__.'/RedisSingle.php';
use \GatewayWorker\Lib\Gateway;
use Clue\React\Redis\Factory;
use Clue\React\Redis\Client;
use Workerman\Worker;
class MyEvent
{
    protected static $code = [
        'ERROR'           =>0, //失败  客户端会进行用户退出操作
        'SUCCESS'         =>1, //成功  返回用户列表
        'LOGIN'           =>2, //登录
        'ISLOGIN'         =>3, //检测登录
        'SERVERMSG'       =>4, //服务端发送消息
        'CLIENTMSG'       =>5, //接收客户端消息
        'CLIENTGETMSG'    =>6, //客户端发送获取用户间的历史聊天记录的请求
        'SERVERSETMSG'    =>7, //服务端发送用户间的历史聊天消息
        
        'TEXTMSG'         =>10, //文本消息
        'IMGMSG'          =>11, //图片消息
        'CLIENTCLOSE'     =>13, //用户离线通知
        'CLIENTCONTENT'   =>14, //用户上线通知
        'UNREADMSG'       =>15, //未读消息处理
        'ADDUNREADMSG'    =>16, //未读消息数量+1
        'DELUNREADMSG'    =>17, //未读消息数量清空
        'SERVERUNREADNUM' =>18, //服务端向客户端发送未读消息数量

        'PINGINTERVAL'    =>20, //心跳检测

    ];

    public static function onWorkerStart($businessWorker)
    {
        global $redis;
        $redis = RedisSingle::getRedis();
        global $db;
        $db = new \Workerman\MySQL\Connection(\Yaconf::get('mysql.host'), \Yaconf::get('mysql.port'), \Yaconf::get('mysql.user'), \Yaconf::get('mysql.password'), 'worker');
    }
	
    public static function onConnect($client_id)
    {
        // 群聊，转发请求给其它所有的客户端
        echo "connect_id::$client_id".PHP_EOL;
    }


    public static function onMessage($client_id, $message)
    {
        // 群聊，转发请求给其它所有的客户端

        $message = json_decode($message,true);
        if (!isset($message['code'])) {
            $data = json_encode(['code'=>self::$code['ERROR'],'msg'=>'code为必传项。']);
            return GateWay::sendToClient($client_id,$data);
        }
        switch ($message['code']) {
            case self::$code['ISLOGIN']:
                    //验证登录
                    try{
                        self::isLogin($client_id,$message); 
                    }catch(\Exception $e){
                        $data = json_encode(['code'=>self::$code['ERROR'],'msg'=>$e->getMessage()]);
                        // var_dump($e->getMessage());
                        return GateWay::sendToClient($client_id,$data);
                    }
                    
                break;
            case self::$code['CLIENTMSG']:
                    //消息过滤
                    if (!$textContent = self::msgFilter($message)) {
                        var_dump('过滤后消息为空');
                        break;
                    }
                    var_dump($textContent);
                    $message['textContent'] = $textContent;
                    //给指定id的用户发送消息
                    $data = json_encode(['code'=>self::$code['SERVERMSG'],'textContent'=>$message['textContent'],'id'=>$message['uid'],'type'=>$message['type'],'create_date'=>$message['create_date'],'isgroup'=>$message['isgroup'],'uname'=>$message['uname']]);
                    //如果是群聊
                    if ($message['isgroup']) {
                        Gateway::sendToGroup($message['gid'],$data,$client_id);
                    }else{
                        Gateway::sendToUid($message['gid'],$data);
                    }
                    

                    //mysql存储双方聊天信息
                    $msgId = self::setMysqlMsg($message);
                    if (!$msgId) {
                        echo "储存聊天记录到mysql失败".PHP_EOL;
                    }
                    $message['msgId'] = $msgId;

                    //redis储存双方聊天信息
                    self::setRedisMsg($message);
                break; 
            case self::$code['CLIENTGETMSG']:
                //获取聊天记录
                // var_dump($message);
                $msgList = self::getMsg($message);
                
                $data = json_encode(['code'=>self::$code['SERVERSETMSG'],'msg'=>'历史聊天记录','data'=>$msgList]);
                GateWay::sendToUid($message['uid'],$data);
                
                break;

            case self::$code['PINGINTERVAL']:
                    //心跳检测，不做任何处理

                break;

            case self::$code['UNREADMSG']:
                    //未读消息处理
                    self::unreadMsg($message);                    
                break;
            default:
                echo "null123";
                break;
        }
        // return GateWay::sendToAll($message);
    }

    /**
     * 当用户断开连接时触发的方法
     * @param integer $client_id 断开连接的客户端client_id
     * @return void
     */
    public static function onClose($client_id)
    {
        //获取离线的用户id
        $uid =  $_SESSION[$client_id] ;
        // 广播 用户 离线
        if(!empty($uid)){
            $data = ['code'=>self::$code['CLIENTCLOSE'],'uid'=>$uid];
            GateWay::sendToAll(json_encode($data));
            //吧用户的登录状态改为0(离线)
            try{
                self::reLoginStatus($uid,0);
            }catch(\Exception $e){
                echo $e->getMessage().PHP_EOL;
            }
        }
    }

    /**
     * 判断用户是否是已经登录的用户
     * @param  [type]  $client_id [description]
     * @param  [type]  $message   [description]
     * @return boolean            [description]
     */
    public static function isLogin($client_id,$message){

        global $redis;
        //是否断开用户连接的凭证
        $static = true;
        //用户信息为空
        if (empty($message['key'])) {
            $data = json_encode(['code'=>self::$code['ERROR'],'msg'=>'有必填信息未填']);
            $static = false;
            return GateWay::sendToClient($client_id,$data);
        }
        //持久登录验证
        $status = $redis->get($message['key']);
        if (!$status) {
           $data = json_encode(['code'=>self::$code['ERROR'],'msg'=>'登录失败123']);
            $static = false;
            return GateWay::sendToClient($client_id,$data);
        }
        //关闭该用户链接
        if (!$static) {
            Gateway::closeClient($client_id);
        }

        //吧用户的登录状态改为1(在线)
        self::reLoginStatus($message['uid'],1);
        //获取对应的用户列表
        $userList = self::getList($message['identity'],$message['uid']);
        //获取用户所在群列表
        $groupList = self::getGroupList($message['uid']);
        //给某个群组添加用户的client_id，用户群聊消息广播
        foreach($groupList as $item){
            Gateway::joinGroup($client_id, $item['id']);
        }
        $data = json_encode(['code'=>self::$code['SUCCESS'],'msg'=>'登录成功','data'=>['userList'=>$userList,'groupList'=>$groupList]]);
        //吧client_id和用户的id进行绑定
        Gateway::bindUid($client_id, $message['uid']);

        //在onclose中无法根据client_id获取用户id所以需要在session中记录
        $_SESSION[$client_id] = $message['uid'];
        //广播，用户上线通知
        $loginData = json_encode(['code'=>self::$code['CLIENTCONTENT'],'uid'=>$message['uid']]);
        Gateway::sendToAll($loginData);

        return GateWay::sendToClient($client_id,$data);
    }

    /**
     * 获取用户列表，客户获取客服列表，客服获取用户列表
     * @param  integer $identity [description]
     * @return [type]            [description]
     */
    public static function getList($identity = 0,$uid){
        global $db,$redis;
        $identity = $identity ? 0 : 1;
        $list =$redis->get('userList_'.$identity);
        if (empty($list)) {
            $list = $db->select('id,name,status,avatarUrl as img')->from('user')->where('identity= :identity ')->bindValues(array('identity'=>$identity))->query();
            $list_id = array_column($list, 'id');
            $list = array_combine($list_id,$list);
            $redis->set('userList_'.$identity,json_encode($list));
        }else{
            $list = json_decode($list,true);
        }
        sort($list);
        //用户未读消息数量
        $list = self::getListUnread($list,$uid);

        return $list;

    }
    /**
     * 给用户列表添加群列表
     * @param  array   $list [description]
     * @param  integer $uid  [description]
     * @return [type]        [description]
     */
    public static function getGroupList($uid = 0){
        global $db,$redis;
        if (empty($uid)) {
            return [];
        }
        $groupListInfo = $redis->get('userGroup_'.$uid);
        if (empty($groupListInfo)) {
            //获取用户添加的群，用户可能未加入任何群
            $groupList = $db->select('gid')->from('group_users')->where('uid= :uid ')->bindValues(array('uid'=>$uid))->column();
            //获取群信息,
            if(is_array($groupList)){
                $groupListInfo = $db->select('id,name,img')->from('group')->where('id in('.implode(',', $groupList).') ')->query();
            }else{
                $groupListInfo = [];
            }

            //群信息添加到redis
            $redis->set('userGroup_'.$uid,json_encode($groupListInfo));
        }else{
            $groupListInfo = json_decode($groupListInfo,true);
        }
        //绑定群未读消息数量
        $groupListInfo = self::getListUnread($groupListInfo,$uid,1);
        return $groupListInfo;
    }


    /**
     * 保存数据到mysql中返回添加的id
     * @param [type] $message [description]
     */
    public static function setMysqlMsg($message){
        global $db;
        if (!$message['uid'] || !$message['gid'] || !$message['textContent'] || !$message['create_date']) {
            return false;
        }
        $from = $message['isgroup'] ?'group_msg':'msg';
        $arr =  array(
            'uid'=>$message['uid'],
            'gid'=>$message['gid'],
            'textContent'=>$message['textContent'],
            'create_date'=>$message['create_date']
        );
        //如果是群聊添加一个uname（发送者名称）的键
        if ($message['isgroup'] ) {
            $arr['uname'] = $message['uname'];
        }
        //返回mysql 添加后的id
        $insert_id = $db->insert($from)->cols($arr)->query();
        return $insert_id;
    }   

    /**
     * 保存数据到redis的有序集合中
     * @param $message
     * @return bool
     */
    public static function setRedisMsg($message){
        global $redis;
        
        if (!$message['uid'] || !$message['gid'] || !$message['textContent'] || !$message['create_date']) {
            return false;
        }
        $jsonMessage = json_encode($message);
        //储存消息到redis。
        //吧 客服1 和 用户1 的对话不管是谁发送的消息都储存到同一个键里。
        if ($message['isgroup'] ) {
            $redis->Zadd('group_msg_'.$message['gid'],$message['msgId'],$jsonMessage);
        }else{
            $redisKey = 'msg_'.$message['uid'].'_'.$message['gid'];
            $msgList = $redis->zrange($redisKey,0,-1);
            if ($msgList) {
                $redis->Zadd($redisKey,$message['msgId'],$jsonMessage);
            }else{
                $redisKey = 'msg_'.$message['gid'].'_'.$message['uid'];
                $redis->Zadd($redisKey,$message['msgId'],$jsonMessage);
            }
        }
        
    }


    /**
     * 获取聊天记录
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function getMsg($data){
        global $redis;
        if (!$data['uid'] || !$data['gid']) {
            return false;
        }
        if ($data['isgroup']) {
            //群聊的
            $redisKey = 'group_msg_'.$data['gid'];
            $msgList = self::redsiKeyAll('zrange',$redisKey);
        }else{
            //好友的
            $redisKey = 'msg_'.$data['uid'].'_'.$data['gid'];
            //获取redis中的聊天数据
            $msgList = self::redsiKeyAll('zrange',$redisKey);
            if (empty($msgList)) {
                $redisKey = 'msg_'.$data['gid'].'_'.$data['uid'];
                 //获取redis中的聊天数据
                $msgList = self::redsiKeyAll('zrange',$redisKey);
            }
        }
        

        if (empty($msgList)) {
            $msgList = self::mysqlKeyAll($data);

            if(!empty($msgList)){
                foreach ($msgList as $message) {
                    $redis->Zadd($redisKey,$message['id'],json_encode($message));
                }
            }
        }
        return $msgList;

    }
    /**
     * 获取redis数据
     * @param  [type]  $method [description]
     * @param  [type]  $key    [description]
     * @param  integer $start  [description]
     * @param  integer $stop   [description]
     * @return [type]          [description]
     */
    public static function redsiKeyAll($method,$key,$start = 0,$stop = -1){
        global $redis;
        if (empty($method)||empty($key)) {
            return false;
        }
        $arr = $redis->$method($key,$start,$stop);
        return $arr;
    }


    /**
     * 获取mysql中的用户聊天记录
     * @param $data
     * @param int $limit
     * @param int $offset
     * @return bool
     */
    public static function mysqlKeyAll($data,$limit=30,$offset=0){
        global $db;
        if (!$data['uid'] || !$data['gid']) {
            return false;
        }
        if (!$data['isgroup']) {
            $msgList = $db->select('*')->from('msg')->where('(uid = :uid AND gid = :gid) OR (uid = :regid AND gid = :reuid)')
        ->bindValues(array('uid'=>$data['uid'], 'gid' => $data['gid'] ,'regid'=>$data['gid'], 'reuid' => $data['uid']))
        ->orderByASC(array('id'))->limit($limit)->offset($offset)->query();
        }else{
            $msgList = $db->select('*')->from('group_msg')->where('gid = :gid')
        ->bindValues(array('gid' => $data['gid']))
        ->orderByASC(array('id'))->limit($limit)->offset($offset)->query();
        }
        return $msgList;
    }

    /**修改用户登录状态
     * @param $id   [用户id]
     * @param int $status   [修改后的状态值]
     * @throws Exception    [description]
     */
    public static function reLoginStatus($id,$status=0){
        global $redis,$db;
        if (!is_numeric($id)) {
            throw new \Exception("传入的第一个参数应为数字", 1);
        }
        //修改MySQL中的用户在线状态
        $db->update('user')->cols(array('status'))->where('id='.$id)
        ->bindValue('status', $status)->query();
        //获取用户的身份（客服，还是用户）。
        $row = $db->select('identity')->from('user')->where('id= :id')->bindValues(array('id'=>$id))->row();
        //修改redis中用户的在线状态
        $list = $redis->get('userList_'.$row['identity']);
        if (!empty($list)) {
            $list = json_decode($list,true);
            $list[$id]['status'] = $status;
            $list = json_encode($list);
            $redis->set('userList_'.$row['identity'],$list);
        }
    }

    /**
     * 数据过滤
     * @param  [type] $message [description]
     * @return [type]          [description]
     */
    public static function msgFilter($message){
        $textContent = trim($message['textContent']);
        $textContent = htmlspecialchars_decode($textContent);
        $textContent = strip_tags($textContent);
        switch ($message['type']) {
            case self::$code['TEXTMSG']:
                return $textContent;
            case self::$code['IMGMSG']:
                //消息是图片，直接保存到本地。返回一个图片地址
                $textContent = self::addImg($textContent);
                return $textContent;
            default:
                # code...
                break;
        }
    }

    /**
     * 未读消息处理
     * @param $message
     */
    public static function unreadMsg($message){
        $gid     = $message['gid'];
        $uid     = $message['uid'];
        $isgroup = $message['isgroup'] ?? 0;
        switch ($message['type']) {
            case self::$code['ADDUNREADMSG']:
                //发送消息对方未读消息+1
                 self::setAddUnread($gid,$uid,$isgroup);
                 break;
            case self::$code['DELUNREADMSG']:
                //清空用户和好友之间的未读消息
                //清空的是自己的。
                self::setDelUnread($uid,$gid,$isgroup);
                break;
            default:
                # code...
                break;
        }
    }

    /**
     * 对好友或群的未读消息数量+1
     * @param $uid
     * @param $gid
     * @param $isgroup
     * @return int
     */
    public static function setAddUnread($uid,$gid,$isgroup){
        global $redis;
        if (!$uid || !$gid) {
            return 0;
        }
        if ($isgroup){
            $usersId = self::groupUsers($uid);
            foreach ($usersId as $item){
                if ($item['id'] != $gid){
                    $unread = $redis->incr('unread_group_msg_'.$item['id'].'_'.$uid);
                    //未读数量同步客户端,
                    Gateway::sendToUid($item['id'],json_encode(['code'=>self::$code['SERVERUNREADNUM'],'gid'=>$uid,'unread'=>$unread,'isgroup'=>$isgroup]));
                }
              }


        }else{
            //redis
            $unread = $redis->incr('unread_msg_'.$uid.'_'.$gid);
            //未读数量同步客户端,

            Gateway::sendToUid($uid,json_encode(['code'=>self::$code['SERVERUNREADNUM'],'gid'=>$gid,'unread'=>$unread,'isgroup'=>$isgroup]));

        }

    }
    /**
     * 清空未读消息为0
     * @param $uid
     * @param $gid
     * @param $isgroup
     * @return int
     */
    public static function setDelUnread($uid,$gid,$isgroup){
        global $redis;
        if (!$uid || !$gid) {
            return 0;
        }
        $prefix = $isgroup ? 'unread_group_msg_' : 'unread_msg_';
        $redis->set($prefix.$uid.'_'.$gid,0);
        //清空未读消息,
        Gateway::sendToUid($uid,json_encode(['code'=>self::$code['SERVERUNREADNUM'],'gid'=>$gid,'unread'=>0,'isgroup'=>$isgroup]));
    }


    /**
     * 给好友列表绑定未读消息数量
     * @param  [type]  $list [用户数组]
     * @param  integer $uid  [用户id]
     * @param  [type] $type [区分是获取好友或群组]
     * @return [type]        [description]
     */
    public static function getListUnread($list,$uid=0,$type=0){
        
        if (is_array($list) && $uid) {
            foreach($list as &$key){
                if (!empty($key['id'])) {
                    //获取用户和好友的未读消息数量
                   $key['unread'] = self::getunread($uid,$key['id'],$type);
                }
            }
        }
        return $list;
    }

    /**
     * 获取未读消息数量
     * @param  [type] $uid [用户id]
     * @param  [type] $gid [好友id|群id]
     * @param  [type] $type [区分是获取好友或群组]
     * @return [type]      [未读消息数量]
     */
    public static function getunread($uid,$gid,$type=0){
        global $db,$redis;
        if (!$uid || !$gid) {
           return 0;
        }
        //redis键前缀
        $prefix = $type ? 'unread_group_msg_' : 'unread_msg_';
        //表名
        $from =  $type ? 'unread_group_msg' : 'unread_msg';
        $unread = $redis->get($prefix.$uid.'_'.$gid);
        if (!is_numeric($unread)) {
            $unread = $db->select('unread')->from($from)->where('uid= :uid AND gid= :gid')->bindValues(array('uid'=>$uid,'gid'=>$gid))->single();
            $unread = $unread ? $unread : 0;
            $redis->set($prefix.$uid.'_'.$gid,$unread);
        }
        return $unread ;
    }



    /**
     * 获取群成员
     * @param  [type] $gid [description]
     * @return [type]      [description]
     */
    public static function groupUsers($gid){
       global $db,$redis;
       if (!$gid) {
           return [];
        }

        $group_users = $redis->get('group_users_'.$gid);
        if (empty($group_users)) {
            $group_users_id = $db->select('uid')->from('group_users')->where('gid= :gid')->bindValues(array('gid'=>$gid))->column();

            $group_users = $db->select('id,name')->from('user')->where("id in(".implode(',',$group_users_id).")")->query();

            $redis->set('group_users_'.$gid,json_encode($group_users));
        }else{
            $group_users = json_decode($group_users,true);
        }
        return $group_users;

    }

    /**
     *把图片保存到服务器上
     * @param $content
     * @return string
     */
    public static function addImg($content){
        $key = strstr($content,'base64,');
        //判断他是否是一个 通过 FileReader 读取的文件内容
        if ($key == false){
            return  $content;
        }
        list($imgType,$content) = explode(',', $content);
        if(stristr($imgType,'image/jpeg')!==''){
            $ext = '.jpg';
        }elseif(strstr($imgType,'image/gif')!==''){
            $ext = '.gif';
        }elseif(strstr($imgType,'image/png')!==''){
            $ext = '.png';
        }
        $fileRoot="/var/www/html/workerman";
        $fileDir="/web/img/static/msg/".date('Ymd').'/';
        $filename = date('his').$ext;
        //检测目录是否存在，不存在创建目录
        if (!is_dir($fileRoot.$fileDir)){
            mkdir($fileRoot.$fileDir,0777);
        }
        file_put_contents($fileRoot.$fileDir.$filename ,base64_decode($content),true);
        return $fileDir.$filename;
    }

}
















