<?php
use Monolog\Logger;
use Roiwk\Rabbitmq\Producer;
use Bunny\Channel;
use Bunny\Message;
use Workerman\Worker;
use Roiwk\Rabbitmq\AbstractConsumer;

if (file_exists(__DIR__ . '/../../../../../vendor/autoload.php')) {
    require __DIR__ . '/../../../../../vendor/autoload.php';
} else {
    require __DIR__ . '/../../vendor/autoload.php';
}

$worker = new Worker();

$worker->onWorkerStart = function()  {

    $config = require __DIR__ . '/../config.php';
    $log = require __DIR__ . '/../log.php';

    Producer::connect($config, $log)->publishAsync('Hello World!', '', '', 'hello');

};
Worker::runAll();