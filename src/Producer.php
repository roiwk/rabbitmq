<?php

namespace Roiwk\Rabbitmq;

use Bunny\Channel;
use Bunny\Client;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Roiwk\Rabbitmq\WorkermanRabbitMQClient as AsyncClient;

class Producer
{
    protected array $exchangeDeclareDefault = [
        'passive' => false,
        'durable' => true,
        'auto_delete' => false,
        'internal' => false,
        'nowait' => false,
        'arguments' => [],
    ];

    protected array $queueDeclareDefault = [
        'passive' => false,
        'durable' => true,
        'exclusive' => false,
        'auto_delete' => false,
        'nowait' => false,
        'arguments' => [],
    ];

    protected array $exchangeDeclare = [];

    protected array $queueDeclare = [];

    public function __construct(
        protected array $rabbitmqConfig,
        protected ?LoggerInterface $logger = null)
    {
    }

    public static function connect(array $rabbitmqConfig, LoggerInterface $logger = null): self
    {
        return new self($rabbitmqConfig, $logger);
    }

    protected function setDeclare(array $exchangeOrQueueDeclare, string $exchange = '', string $exchangeType = '')
    {
        if (!empty($exchange) && !empty($exchangeType)) {
            $this->exchangeDeclare = array_replace_recursive($this->exchangeDeclareDefault, $exchangeOrQueueDeclare);
        } else {
            $this->queueDeclare = array_replace_recursive($this->queueDeclareDefault, $exchangeOrQueueDeclare);
        }
    }

    protected function declare(Channel $channel, string $routingOrQueue, string $exchange = '', string $exchangeType = '')
    {
        if (!empty($exchange) && !empty($exchangeType)) {
            return $channel->exchangeDeclare($exchange, $exchangeType,
                $this->exchangeDeclare['passive'], $this->exchangeDeclare['durable'],
                $this->exchangeDeclare['auto_delete'], $this->exchangeDeclare['internal'],
                $this->exchangeDeclare['nowait'], $this->exchangeDeclare['arguments']);
        } else {
            return $channel->queueDeclare($routingOrQueue,
                $this->queueDeclare['passive'], $this->queueDeclare['durable'],
                $this->queueDeclare['exclusive'], $this->queueDeclare['auto_delete'],
                $this->queueDeclare['nowait'], $this->queueDeclare['arguments']);
        }
    }

    public function publishAsync(string $data, string $exchange = '', string $exchangeType = '', string $routingOrQueue = '',
        array $exchangeOrQueueDeclare = [], array $headers = [], bool $mandatory = false, bool $immediate = false
    ) {
        $this->setDeclare($exchangeOrQueueDeclare, $exchange, $exchangeType);

        $reject = function (\Throwable $throwable) {
            $this->logger?->error('['.getmypid().']:'.$throwable->getMessage().PHP_EOL.$throwable->getTraceAsString(), [__CLASS__]);
        };
        (new AsyncClient($this->rabbitmqConfig, $this->logger))->connect()
            ->then(function (AsyncClient $client) {
                return $client->channel();
            }, $reject)
            ->then(function (Channel $channel) use ($exchange, $exchangeType, $routingOrQueue) {
                return $this->declare($channel, $routingOrQueue, $exchange, $exchangeType)
                    ->then(function () use ($channel) {
                        return $channel;
                    });
            }, $reject)
            ->then(function (Channel $channel) use ($exchange, $routingOrQueue, $data, $headers, $mandatory, $immediate) {
                $this->logger?->info('('.getmygid().') Sending :'.$data, [__CLASS__]);

                return $channel->publish($data, $headers, $exchange, $routingOrQueue, $mandatory, $immediate)
                    ->then(function () use ($channel) {
                        return $channel;
                    });
            }, $reject)
            ->then(function (Channel $channel) use ($data) {
                $this->logger?->info('('.getmygid().') Sent :'.$data, [__CLASS__]);

                $client = $channel->getClient();

                return $channel->close()->then(function () use ($client) {
                    return $client;
                });
            }, $reject)
            ->then(function (AsyncClient $client) {
                $client->disconnect();
            }, $reject);
    }

    public function publishSync(string $data, string $exchange = '', string $exchangeType = '', string $routingOrQueue = '',
        array $exchangeOrQueueDeclare = [], array $headers = [], bool $mandatory = false, bool $immediate = false
    ) {
        $this->setDeclare($exchangeOrQueueDeclare, $exchange, $exchangeType);

        $rabbitmqConfig = Arr::only($this->rabbitmqConfig, ['host', 'port', 'vhost', 'user', 'password']);

        try {
            $client = (new Client($rabbitmqConfig))->connect();
            $channel = $client->channel();
            $this->declare($channel, $routingOrQueue, $exchange, $exchangeType);
            $published = $channel->publish($data, $headers, $exchange, $routingOrQueue, $mandatory, $immediate);
        } catch (\Throwable $throwable) {
            $this->logger?->error('['.getmypid().']:'.$throwable->getMessage().PHP_EOL.$throwable->getTraceAsString(), [__CLASS__]);
        } finally {
            isset($channel) && $channel->close();
            isset($client) && $client->disconnect();
        }

        return $published ?? false;
    }
}