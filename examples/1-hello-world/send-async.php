<?php

use Roiwk\Rabbitmq\Producer;
use Workerman\Timer;
use Workerman\Worker;

if (file_exists(__DIR__ . '/../../../../../vendor/autoload.php')) {
    require __DIR__ . '/../../../../../vendor/autoload.php';
} else {
    require __DIR__ . '/../../vendor/autoload.php';
}

// $_ENV['REVOLT_DRIVER'] = \Revolt\EventLoop\Driver\StreamSelectDriver::class;

$worker = new Worker();
// $worker->eventLoop = \Workerman\Events\Revolt::class;
// $worker->count = 4;

$worker->onWorkerStart = function()  {

        $config = require __DIR__ . '/../config.php';
        $log = require __DIR__ . '/../log.php';

        Timer::add(1, function() use ($config, $log) {
            Producer::connect($config, $log)->publishAsync('Hello World!', '', '', 'hello');
        });
};
Worker::runAll();