<?php

require_once '../vendor/autoload.php';
use \GatewayWorker\Lib\Gateway;
use Clue\React\Redis\Factory;
use Clue\React\Redis\Client;
use Workerman\Worker;
class MyEvent
{
    protected static $code = [
        'SUCCESS'=>1, //成功
        'ERROR'=>0, //失败
        'LOGIN'=>2, //登录
        'ISLOGIN'=>3, //检测登录
        'SERVERMSG'=>4,//服务端发送消息
        'CLIENTMSG'=>5,//接收客户端消息

    ];
    public static function onWorkerStart($businessWorker)
    {
        global $factory;
        $loop    = Worker::getEventLoop();
        $factory = new Factory($loop);
        $factory = $factory->createLazyClient('localhost:6379');
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
            $data = json_encode(['code'=>self::$code['ERROR'],'msg'=>'登录失败']);
            return GateWay::sendToClient($client_id,$data);
        }
        switch ($message['code']) {
            case self::$code['ISLOGIN']:
                    //验证登录
                    self::isLogin($client_id,$message);
                break;
            case self::$code['CLIENTMSG']:
                    //给帮定id的用户发送消息
                    $data = json_encode(['code'=>self::$code['SERVERMSG'],'textContent'=>$message['textContent']]);
                    Gateway::sendToUid($message['Gid'],$data);

                    //mysql存储双发聊天信息
                    $msgId = self::setMysqlMsg($message);
                    if (!$msgId) {
                        echo "储存聊天记录到mysql失败".PHP_EOL;
                    }
                    $message['msgId'] = $msgId;
                    //redis储存双方聊天信息
                    self::setRedisMsg($message);
                break; 
  
            default:
                echo "null123";
                break;
        }
        // return GateWay::sendToAll($message);

    }

    /**
     * 判断用户是否是已经登录的用户
     * @param  [type]  $client_id [description]
     * @param  [type]  $message   [description]
     * @return boolean            [description]
     */
    public static function isLogin($client_id,$message){
        global $factory;
        $static = true;
        if (empty($message['key'])) {
            $data = json_encode(['code'=>self::$code['ERROR'],'msg'=>'登录失败']);
            $static = false;
            return GateWay::sendToClient($client_id,$data);
        }
        $status = $factory->get($message['key']);
        if (!$status) {
           $data = json_encode(['code'=>self::$code['ERROR'],'msg'=>'登录失败']);
            $static = false;
            return GateWay::sendToClient($client_id,$data);
        }
        if (!$static) {
            Gateway::closeClient($client_id);
        }
        //获取对应的用户列表
        $list = self::getList($message['identity']);
        $data = json_encode(['code'=>self::$code['SUCCESS'],'msg'=>'登录成功','data'=>$list]);
        Gateway::bindUid($client_id, $message['id']);
        return GateWay::sendToClient($client_id,$data);
    }

    /**
     * 获取用户列表，客户获取客服列表，客服获取用户列表
     * @param  integer $identity [description]
     * @return [type]            [description]
     */
    public static function getList($identity = 0){
        global $db;
        $identity = $identity ? 0 : 1;
        $list = $db->select('id,name')->from('user')->where('identity= :identity ')->bindValues(array('identity'=>$identity))->query();
        return $list;

    }
    /**
     * 保存数据到mysql中返回添加的id
     * @param [type] $message [description]
     */
    public static function setMysqlMsg($message){
        global $db;
        if (!$message['id'] || !$message['Gid'] || !$message['textContent']) {
            return false;
        }
        $insert_id = $db->insert('msg')->cols(array(
                        'uid'=>$message['id'],
                        'gid'=>$message['Gid'],
                        'textContent'=>$message['textContent']
                    ))->query();
        return $insert_id;
    }   
    /**
     * 保存数据到redis的有序集合中
     * @param [type] $message [description]
     */
    public static function setRedisMsg($message){
        global $factory;
        
        if (!$message['id'] || !$message['Gid'] || !$message['textContent']) {
            return false;
        }
        $jsonMessage = json_encode($message);
        //储存消息到redis。
        //吧 客服1 和 用户1 的对话不管是谁发送的消息都储存到同一个键里。
        $redisKey = 'msg_'.$message['id'].'_'.$message['Gid'];
        $factory->zrange($redisKey,0,-1)->then(
            function($item) use($redisKey,$jsonMessage,$message,$factory){
                    if ($item){
                        $factory->Zadd($redisKey,$message['msgId'],$jsonMessage);
                    }else{
                        $redisKey = 'msg_'.$message['Gid'].'_'.$message['id'];
                        $factory->Zadd($redisKey,$message['msgId'],$jsonMessage);
                    }
                    return;
            }
        );
        
        


    }

}
















