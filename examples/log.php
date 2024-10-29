<?php

if (class_exists(Monolog\Logger::class)) {
    $log = new Monolog\Logger('test');
    $log->pushHandler(new Monolog\Handler\StreamHandler('php://stdout', Monolog\Logger::DEBUG));
    return $log;
} else {
    return null;
}