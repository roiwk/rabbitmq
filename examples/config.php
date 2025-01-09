<?php

return [
    'host' => '172.16.30.222',
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