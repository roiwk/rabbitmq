<?php
use Bunny\Channel;
use Workerman\Worker;
use Roiwk\Rabbitmq\Producer;
use Roiwk\Rabbitmq\AbstractConsumer;

if (file_exists(__DIR__ . '/../../../../../vendor/autoload.php')) {
    require __DIR__ . '/../../../../../vendor/autoload.php';
} else {
    require __DIR__ . '/../../vendor/autoload.php';
}

$worker = new Worker();

$worker->onWorkerStart = function() {
    global $argv;
    unset($argv[1]);
    $argv = array_values($argv);
    $routing_key = isset($argv[1]) && !empty($argv[1]) ? $argv[1] : 'info';
    $data = implode(' ', array_slice($argv, 2));
    if (empty($data)) {
        $data = "Hello World!";
    }

    $config = require __DIR__ . '/../config.php';
    $log = require __DIR__ . '/../log.php';

    Producer::connect($config, $log)->publishAsync($data, 'topic_logs', 'topic', $routing_key);

};

Worker::runAll();
