<?php

return [
    'host' => '127.0.0.1',
    'port' => 5672,
    'vhost' => '/',
    'mechanism' => 'AMQPLAIN',
    'user' => 'admin',
    'password' => '123456',
    'timeout' => 10,
    'heartbeat' => 60,
    'heartbeat_callback' => function(){},
    'error_callback' => null,
];