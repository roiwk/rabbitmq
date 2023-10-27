<?php


use Roiwk\Rabbitmq\Producer;

if (file_exists(__DIR__ . '/../../../../../vendor/autoload.php')) {
    require __DIR__ . '/../../../../../vendor/autoload.php';
} else {
    require __DIR__ . '/../../vendor/autoload.php';
}


$data = implode(' ', array_slice($argv, 1));


$config = require __DIR__ . '/../config.php';
$log = require __DIR__ . '/../log.php';

Producer::connect($config, $log)->publishSync($data, '', '', 'task_queue', [], ['delivery-mode' => 2]);

echo " [x] Sent '{$data}'\n";

