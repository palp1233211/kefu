<?php

require_once '../vendor/autoload.php';

use GatewayWorker\Gateway;
use Workerman\Worker;

$worker  = new Gateway("websocket://0.0.0.0:2345");

$worker->name = 'Gateway';

$worker->count = 2;

$worker->lanIp = '127.0.0.1';

$worker->registerAddress = '127.0.0.1:7777';

$worker->pingInterval = 50;//心跳检测时间间隔 单位：秒。如果设置为0代表不做任何心跳检测。

$worker->pingNotResponseLimit = 1;//客户端连续$pingNotResponseLimit次$pingInterval时间内不发送任何数据则断开链接，并触发onClose

$data = json_encode(['code'=>20,'msg'=>'']);

$worker->pingData = $data;//设置为服务端要发送的心跳请求数据，心跳数据是任意的，只要客户端能识别即可。

Worker::runAll();






