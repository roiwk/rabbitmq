<?php

use Roiwk\Rabbitmq\Producer;

if (file_exists(__DIR__ . '/../../../../../vendor/autoload.php')) {
    require __DIR__ . '/../../../../../vendor/autoload.php';
} else {
    require __DIR__ . '/../../vendor/autoload.php';
}

$routing_key = isset($argv[1]) && !empty($argv[1]) ? $argv[1] : 'info';
$data = implode(' ', array_slice($argv, 2));
if (empty($data)) {
    $data = "Hello World!";
}


$config = require __DIR__ . '/../config.php';
$log = require __DIR__ . '/../log.php';

Producer::connect($config, $log)->publishSync($data, 'topic_logs', 'topic', $routing_key);
echo " [x] Sent ",$routing_key,':',$data," \n";

