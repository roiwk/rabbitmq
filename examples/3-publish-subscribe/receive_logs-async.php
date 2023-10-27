<?php

use Bunny\Channel;
use Bunny\Message;
use Bunny\AbstractClient;
use Workerman\Worker;
use Roiwk\Rabbitmq\Producer;
use Roiwk\Rabbitmq\AbstractConsumer;

if (file_exists(__DIR__ . '/../../../../../vendor/autoload.php')) {
    require __DIR__ . '/../../../../../vendor/autoload.php';
} else {
    require __DIR__ . '/../../vendor/autoload.php';
}

$worker = new Worker();

$config = require __DIR__ . '/../config.php';
$log = require __DIR__ . '/../log.php';

$consumer = new class ($config, $log) extends AbstractConsumer {

    protected bool $async = true;

    protected string $exchange = 'logs';

    protected string $exchangeType = 'fanout';

    protected string $queue = 'log_queue';

    protected array $consume = [
        'noAck' => true,
        'noLocal' => true,
    ];

    public function consume(Message $message, Channel $channel, AbstractClient $client)
    {
        echo " [x] Received ", $message->content, "\n";
    }
};

$worker->onWorkerStart = [$consumer, 'onWorkerStart'];

Worker::runAll();
