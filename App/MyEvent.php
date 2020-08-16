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
        'ERROR'         =>0, //失败  客户端会进行用户退出操作
        'SUCCESS'       =>1, //成功
        'LOGIN'         =>2, //登录
        'ISLOGIN'       =>3, //检测登录
        'SERVERMSG'     =>4, //服务端发送消息
        'CLIENTMSG'     =>5, //接收客户端消息
        'CLIENTGETMSG'  =>6, //客户端发送获取用户间的历史聊天记录的请求
        'SERVERSETMSG'  =>7, //服务端发送用户间的历史聊天消息
        
        'TEXTMSG'       =>10, //文本消息
        'IMGMSG'        =>11, //图片消息
        'CLIENTCLOSE'   =>13, //用户离线通知
        'CLIENTCONTENT' =>14, //用户上线通知
        'PINGINTERVAL'  =>20, //心跳检测

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
                    $message['textContent'] = $textContent;
                    //给指定id的用户发送消息
                    $data = json_encode(['code'=>self::$code['SERVERMSG'],'textContent'=>$message['textContent'],'id'=>$message['uid'],'type'=>$message['type'],'create_date'=>$message['create_date']]);
                    Gateway::sendToUid($message['gid'],$data);

                    //mysql存储双发聊天信息
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
        $list = self::getList($message['identity']);
        $data = json_encode(['code'=>self::$code['SUCCESS'],'msg'=>'登录成功','data'=>$list]);
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
    public static function getList($identity = 0){
        global $db;
        global $redis;
        $identity = $identity ? 0 : 1;
        $list =$redis->get('userList_'.$identity);
        if (empty($list)) {
            $list = $db->select('id,name,status')->from('user')->where('identity= :identity ')->bindValues(array('identity'=>$identity))->query();
            $list_id = array_column($list, 'id');
            $list = array_combine($list_id,$list);
            $redis->set('userList_'.$identity,json_encode($list));

        }else{
            $list = json_decode($list,true);
        }
        sort($list);
        return $list;

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
        $insert_id = $db->insert('msg')->cols(array(
                        'uid'=>$message['uid'],
                        'gid'=>$message['gid'],
                        'textContent'=>$message['textContent'],
                        'create_date'=>$message['create_date']
                    ))->query();
        return $insert_id;
    }   
    /**
     * 保存数据到redis的有序集合中
     * @param [type] $message [description]
     */
    public static function setRedisMsg($message){
        global $redis;
        
        if (!$message['uid'] || !$message['gid'] || !$message['textContent'] || !$message['create_date']) {
            return false;
        }
        $jsonMessage = json_encode($message);
        //储存消息到redis。
        //吧 客服1 和 用户1 的对话不管是谁发送的消息都储存到同一个键里。
        $redisKey = 'msg_'.$message['uid'].'_'.$message['gid'];
        $msgList = $redis->zrange($redisKey,0,-1);
        if ($msgList) {
            $redis->Zadd($redisKey,$message['msgId'],$jsonMessage);
        }else{
            $redisKey = 'msg_'.$message['gid'].'_'.$message['uid'];
            $redis->Zadd($redisKey,$message['msgId'],$jsonMessage);
        }
    }
    /**
     * 获取用户聊天记录
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function getMsg($data){
        global $redis;
        if (!$data['uid'] || !$data['gid']) {
            return false;
        }
        $redisKey = 'msg_'.$data['uid'].'_'.$data['gid'];
        //获取redis中的聊天数据
        $msgList = self::redsiKeyAll('zrange',$redisKey);
        if (empty($msgList)) {
            $redisKey = 'msg_'.$data['gid'].'_'.$data['uid'];
             //获取redis中的聊天数据
            $msgList = self::redsiKeyAll('zrange',$redisKey);
        }
        if (empty($msgList)) {
            echo "string";
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
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function mysqlKeyAll($data,$limit=30,$offset=0){
        global $db;
        if (!$data['uid'] || !$data['gid']) {
            return false;
        }
       $msgList = $db->select('id,uid,gid,textContent,type,create_date')->from('msg')->where('(uid = :uid AND gid = :gid) OR (uid = :regid AND gid = :reuid)')
        ->bindValues(array('uid'=>$data['uid'], 'gid' => $data['gid'] ,'regid'=>$data['gid'], 'reuid' => $data['uid']))
        ->orderByASC(array('id'))->limit($limit)->offset($offset)->query();
        return $msgList;
    }
    /**
     * 修改用户登录状态
     * @param  [type] $id     [用户id]
     * @param  [type] $status [修改后的状态值]
     * @return [type]         [description]
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
        switch ($message['type']) {
            case self::$code['TEXTMSG']:
                $message = trim($message['textContent']);
                $message = htmlspecialchars_decode($message);
                $message = strip_tags($message);
                // $data = htmlspecialchars($data);
                return $message;
            
            default:
                # code...
                break;
        }
    }

}
















