<?php

use Workerman\Worker;
use Roiwk\Rabbitmq\Producer;
use Roiwk\Rabbitmq\AbstractConsumer;

if (file_exists(__DIR__ . '/../../../../../vendor/autoload.php')) {
    require __DIR__ . '/../../../../../vendor/autoload.php';
} else {
    require __DIR__ . '/../../vendor/autoload.php';
}

$worker = new Worker();

$worker->onWorkerStart = function($worker) {

    $config = require __DIR__ . '/../config.php';
    $log = require __DIR__ . '/../log.php';

    global $argv;
    unset($argv[1]);
    $data = implode(' ', array_slice($argv, 1));

    Producer::connect($config, $log)->publishAsync($data, '', '', 'task_queue', [], ['delivery-mode' => 2]);
};


Worker::runAll();
