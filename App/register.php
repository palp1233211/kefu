<?php

require_once '../vendor/autoload.php';

use GatewayWorker\Register;
use Workerman\Worker;

$worke  = new Register("websocket://0.0.0.0:7777");

$worke::runAll();





