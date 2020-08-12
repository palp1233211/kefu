<?php 


require_once '../vendor/autoload.php';
use Clue\React\Redis\Factory;
use Clue\React\Redis\Client;
use Workerman\Worker;

$worker = new Worker('http://0.0.0.0:8686');

define('CODE',[
	'SUCCESS'=>1, //成功
	'ERROR'=>0,	//失败
	'LOGIN'=>2, //登录
])  ;
$worker->onWorkerStart = function($worker)
{
    // 将db实例存储在全局变量中(也可以存储在某类的静态成员中)
    global $db;
    $db = new \Workerman\MySQL\Connection(\Yaconf::get('mysql.host'), \Yaconf::get('mysql.port'), \Yaconf::get('mysql.user'), \Yaconf::get('mysql.password'), 'worker');
    global $factory;
    $loop    = Worker::getEventLoop();
    $factory = new Factory($loop);
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
			login($connection, $data);
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
 	global $factory;
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
			$client = $factory->createLazyClient('localhost:6379');
			$key = md5('User_id_'.$row['id']);
			$client->set($key, $row['id'], 60*60*24*7);
			$msg = ['code'=>CODE['SUCCESS'],'msg'=>'success','data'=>['name'=>$key,'username'=>$row['name'],'Userid'=>$row['id'] , 'identity'=>$row['identity']]];
		}
		
	}else{
		$msg = ['code'=>CODE['ERROR'],'msg'=>'账户或密码不正确','data'=>[]];
	}
		
    $connection->send(json_encode($msg));

}


// function is_login($connection, $key){
// 	global $factory;
// 	if (empty($key)) {
// 		$msg = ['code'=>CODE['ERROR'],'msg'=>'请先登录','data'=>[]];	
// 		$connection->send(json_encode($msg));
// 		return ;
// 	}
// 	$client = $factory->createLazyClient('localhost:6379');
// 	$status = $client->get($key);
// 	if ($status) {
// 		$msg = ['code'=>CODE['ERROR'],'msg'=>'请先登录','data'=>[]];	
// 	}else{
// 		$msg = ['code'=>CODE['SUCCESS'],'msg'=>'登录成功','data'=>[]];
// 	}
// 	$connection->send(json_encode($msg));
// }