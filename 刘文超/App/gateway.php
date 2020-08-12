<?php

require_once '../vendor/autoload.php';

use GatewayWorker\Gateway;
use Workerman\Worker;

$worker  = new Gateway("websocket://0.0.0.0:2345");

$worker->name = 'Gateway';

$worker->count = 2;

$worker->lanIp = '127.0.0.1';

$worker->registerAddress = '127.0.0.1:7777';


Worker::runAll();






