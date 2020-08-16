<?php 


require_once '../vendor/autoload.php';
require_once __DIR__.'/RedisSingle.php';
use Clue\React\Redis\Factory;
use Clue\React\Redis\Client;
use Workerman\Worker;

$worker = new Worker('http://0.0.0.0:8686');

define('CODE',[
	'SUCCESS'=>1, //成功
	'ERROR'=>0,	//失败
	'LOGIN'=>2, //登录
	'WXLOGIN'=>42, //登录
	'ISKICKED'=>8,//验证用户是否在其他地方登录
	'WXUSERLIST'=>43,
])  ;

$worker->onWorkerStart = function($worker)
{
    // 将db实例存储在全局变量中(也可以存储在某类的静态成员中)
    global $db;
    $db = new \Workerman\MySQL\Connection(\Yaconf::get('mysql.host'), \Yaconf::get('mysql.port'), \Yaconf::get('mysql.user'), \Yaconf::get('mysql.password'), 'worker');
    global $redis;
    $redis = RedisSingle::getRedis();


};

$worker->onMessage = function($connection, $request)
{
	$data = $request->post();

	if (empty($data['code'])) {
		$msg = ['code'=>CODE['ERROR'],'msg'=>'您有未传入的数据！','data'=>[]];
		$connection->send(json_encode($msg));
		return;
	}
	switch ($data['code']) {
		case CODE['LOGIN']:
			//登录
			try{
				login($connection, $data);
			}catch(\Exception $e){
				 $msg = ['code'=>CODE['ERROR'],'msg'=>'服务器内部错误','data'=>[]];
				 $connection->send(json_encode($msg));
			}
			
			break;
		case CODE['ISKICKED']:
			//验证用户是否在其他地方登录
			try{
				$msg = iskicked($data['id'],$data['token']);
			}catch(\Exception $e){
				$msg = ['code'=>CODE['ERROR'],'msg'=>'服务器内部错误','data'=>[]];
			}
			$connection->send(json_encode($msg));
			break;
		case CODE['WXLOGIN']:
			//登录
			try{
				wxLogin($connection, $data);
			}catch(\Exception $e){
				 $msg = ['code'=>CODE['ERROR'],'msg'=>'服务器内部错误','data'=>[]];
				 $connection->send(json_encode($msg));
			}
			
			break;

			//代删除
		case CODE['WXUSERLIST']:
			//登录
			
			try{
				
				$identity = islogin($data['id']);

				$list = wxUserList($identity);
        		$data = json_encode(['code'=>CODE['SUCCESS'],'msg'=>'用户列表','data'=>$list]);
			}catch(\Exception $e){
				 $data = ['code'=>CODE['ERROR'],'msg'=>'服务器内部错误','data'=>[]];
				 
			}
			$connection->send($data);
			break;



		default:
			# code...
			break;
	}
	
};




// 运行worker
Worker::runAll();
	/**
	 * 登录认证
	 * @param  [type] $connection [description]
	 * @param  [type] $data       [description]
	 * @return [type]             [description]
	 */
	function login($connection, $data){
	 	global $db;
	 	global $redis;
		//判断用户是否发送过来指定的数据

		if (!preg_match("/[a-zA-Z0-9_]{5,12}/",$data['user'])) {
			$msg = ['code'=>CODE['ERROR'],'msg'=>'用户名只能包含字母、数字、下划线！','data'=>[]];
			$connection->send(json_encode($msg));
			return;
		}

		if (!preg_match("/[a-zA-Z0-9_]{5,12}/",$data['password'])) {
			$msg = ['code'=>CODE['ERROR'],'msg'=>'密码只能包含字母、数字、下划线！','data'=>[]];
			$connection->send(json_encode($msg));
			return;
		}


		//查询用户信息
		$row = $db->select('id,identity,name,password,salt')->from('user')->where('user= :user')->bindValues(array('user'=>$data['user']))->row();

		if ($row) {
			if ($row['password'] != md5($row['salt'].md5($data['password']))) {
				$msg = ['code'=>CODE['ERROR'],'msg'=>'密码不正确','data'=>[]];
			}else{
				$key = md5('User_id_'.$row['id']);
				userLasting($row['id'],$key);
				$token = md5($row['id'] . rand(100000,999999));
				userToken($row['id'],$token);

				$msg = ['code'=>CODE['SUCCESS'],'msg'=>'success','data'=>['name'=>$key,'username'=>$row['name'],'Userid'=>$row['id'] , 'identity'=>$row['identity'],'token'=>$token]];

			}
			
		}else{
			$msg = ['code'=>CODE['ERROR'],'msg'=>'账户或密码不正确!','data'=>[]];
		}
			
	    $connection->send(json_encode($msg));
	}
	/**
	 * 微信登录
	 * @param  [type] $connection [description]
	 * @param  [type] $data       [description]
	 * @return [type]             [description]
	 */
	function wxLogin($connection, $data){
		global $redsi,$db;
		$url = 'https://api.weixin.qq.com/sns/jscode2session?appid=wx5fcc196928517ab7&secret=e47f8f11afdc9abc5842dd0d0a84f779&js_code='.$data['wxCode'].'&grant_type=authorization_code';
		try{
			$userData = file_get_contents($url);
			if(empty($userData)){	
				throw new \Exception("未获得openid", 1);
			}
			$userData = json_decode($userData,true);
			$row = $db->select('id,name,identity')->from('user')->where('openid= :openid')->bindValues(array('openid'=>$userData['openid']))->row();
			if (!$row) {
				//微信新用户登录进行注册
				$data['openid'] = $userData['openid'];
				$data['identity'] = 0;//普通用户
				var_dump($data);
				$row = register($data);
				var_dump($row);
			}
		}catch(\Exception $e){
			$msg = ['code'=>CODE['ERROR'],'msg'=>$e->getMessage];
			$connection->send(json_encode($msg));
		}
		
		
			
		$key = md5('User_id_'.$row['id']);
		userLasting($row['id'],$key);
		$token = md5($row['id'] . rand(100000,999999));
		userToken($row['id'],$token);
		$msg = ['code'=>CODE['SUCCESS'],'msg'=>'success','data'=>['name'=>$key,'username'=>$row['name'],'Userid'=>$row['id'] , 'identity'=>$row['identity'],'token'=>$token]];
		$connection->send(json_encode($msg));

	}

	//登录创建token
	function userToken($id,$token){
		global $redis,$db;
		$redis->set('token_'.$id,$token);
		$db->update('user')->cols(array('token'))->where('id='.$id)
			->bindValue('token', $token)->query();
	}
	//持久登录
	function userLasting($id,$key){
		global $redis;
		$redis->set($key, $id, 60*60*24*7);
	}

	/**
	 * 验证账号是否在其他地方登录
	 * @param  [type] $id      [description]
	 * @param  [type] $intoken [description]
	 * @return [type]          [description]
	 */
	function iskicked($id,$intoken){
		global $db;
		global $redis;
		$token = $redis->get('token_'.$id);
		if (empty($token)) {
			$token = $db->select('token')->from('user')->where('id= :id ')->bindValues(array('id'=>$id))->row();
			$redis->set('token_'.$id,$token);
		}
		if ($intoken === $token ) {
			$msg = ['code'=>CODE['SUCCESS'],'msg'=>'success'];
		}else{
			$msg = ['code'=>CODE['ERROR'],'msg'=>'账号已在其他地方登录'];
		}
		return $msg;
	}
	/**
	 * 微信新用户登录进行注册
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	function register($data){
		global $db,$redis;
		$insert_id = $db->insert('user')->cols(array(
                        'avatarUrl'=>$data['avatarUrl'],
                        'name'=>$data['name'],
                        'identity'=>$data['identity'],
                        'openid'=>$data['openid'],
                    ))->query();
		$data['id'] = $insert_id;
		$list = $redis->get('userList_'.$data['identity']);
		if (!empty($list)) {
			$list = json_decode($list,true);
			$list[$insert_id] = ['id'=>$insert_id,'name'=>$data['name'],'status'=>0];
			$redis->set('userList_'.$data['identity'],json_encode($list));
		}
		return $data;
	}
	function wxUserList($identity){
		global $db,$redis;
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
        return $list;;

	}
	function islogin($id){
		global $db;
		$row = $db->select('id,identity')->from('user')->where('id= :id')->bindValues(array('id'=>$id))->row();
		if ($row) {
			return $row['identity'];
		}else{
			throw new \Exception("用户不存在", 1);
			
		}
	}




