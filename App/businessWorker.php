<?php

require_once '../vendor/autoload.php';

use Workerman\Worker;
use \GatewayWorker\BusinessWorker;

$worker  = new BusinessWorker();
$worker->name = 'ChatBusinessWorker';
$worker->count = 4;
$worker->registerAddress = '127.0.0.1:7777';
$worker->eventHandler = 'MyEvent';
$worker::runAll();






