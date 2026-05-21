<?php

namespace Roiwk\Rabbitmq;

use Bunny\Channel;
use Bunny\Client;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;

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

    private static ?array $instances = null;

    private function __construct(
        protected array $rabbitmqConfig,
        protected ?LoggerInterface $logger = null)
    {
    }

    private function __clone()
    {
    }

    public function setLogger(?LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getInstance(array $rabbitmqConfig, ?LoggerInterface $logger = null): self
    {
        ksort($rabbitmqConfig);
        $key = md5(json_encode($rabbitmqConfig));
        if (isset(self::$instances[$key])) {
            $obj = self::$instances[$key];
            $obj->setLogger($logger);

            return self::$instances[$key];
        }

        $obj = new self($rabbitmqConfig, $logger);
        self::$instances[$key] = $obj;

        return self::$instances[$key];
    }

    public static function connect(array $rabbitmqConfig, ?LoggerInterface $logger = null): self
    {
        return self::getInstance($rabbitmqConfig, $logger);
    }

    protected function getAsyncConnect(array $rabbitmqConfig, ?LoggerInterface $logger = null)
    {
        static $connect = null;
        static $client = null;

        if (null === $connect) {
            $client = new AsyncClient($rabbitmqConfig, $logger);
            $connect = $client->connect();
        }

        if ($client->isConnected()) {
            return $connect;
        }
        $connect = $client->connect();

        return $connect;
    }

    protected function getSyncConnect(array $rabbitmqConfig)
    {
        // Bug fix: 不再使用 static 变量缓存连接实例。
        // 在 Workerman 多进程模型下，static 变量在 fork 后会被子进程继承，
        // 继承的 socket fd 已失效但 isConnected() 可能仍返回 true，
        // 导致消息写入到失效 fd 后静默丢失。
        // 每次 publishSync 均新建连接，用完即关，保证可靠性。
        $client = new Client($rabbitmqConfig);
        return $client->connect();
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
        }

        return $channel->queueDeclare($routingOrQueue,
            $this->queueDeclare['passive'], $this->queueDeclare['durable'],
            $this->queueDeclare['exclusive'], $this->queueDeclare['auto_delete'],
            $this->queueDeclare['nowait'], $this->queueDeclare['arguments']);
    }

    public function publishAsync(string $data, string $exchange = '', string $exchangeType = '', string $routingOrQueue = '',
        array $exchangeOrQueueDeclare = [], array $headers = [], bool $mandatory = false, bool $immediate = false,
    ) {
        $reject = function (\Throwable $throwable) {
            $this->logger?->error('['.getmypid().']PUBLIAH ASYNC:'.$throwable->getMessage().PHP_EOL.$throwable->getTraceAsString(), [__CLASS__]);
        };
        try {
            $this->setDeclare($exchangeOrQueueDeclare, $exchange, $exchangeType);
            $this->getAsyncConnect($this->rabbitmqConfig, $this->logger)
                ->then(function (AsyncClient $client) {
                    return $client->channel();
                }, $reject)
                ->then(function (Channel $channel) use ($exchange, $exchangeType, $routingOrQueue) {
                    return $this->declare($channel, $routingOrQueue, $exchange, $exchangeType)
                        ->then(function () use ($channel) {
                            return $channel;
                        })
                    ;
                }, $reject)
                ->then(function (Channel $channel) use ($exchange, $routingOrQueue, $data, $headers, $mandatory, $immediate) {
                    $this->logger?->info('('.getmygid().') Sending :'.$data, [__CLASS__]);

                    return $channel->publish($data, $headers, $exchange, $routingOrQueue, $mandatory, $immediate)
                        ->then(function () use ($channel) {
                            return $channel;
                        })
                    ;
                }, $reject)
                ->then(function (Channel $channel) use ($data) {
                    $this->logger?->info('('.getmygid().') Sent :'.$data, [__CLASS__]);

                    $client = $channel->getClient();

                    return $channel->close()->then(function () use ($client) {
                        return $client;
                    });
                }, $reject)
            ;
        } catch (\Throwable $throwable) {
            $reject($throwable);
        }
    }

    public function publishSync(string $data, string $exchange = '', string $exchangeType = '', string $routingOrQueue = '',
        array $exchangeOrQueueDeclare = [], array $headers = [], bool $mandatory = false, bool $immediate = false,
    ) {
        $this->setDeclare($exchangeOrQueueDeclare, $exchange, $exchangeType);

        $rabbitmqConfig = Arr::only($this->rabbitmqConfig, ['host', 'port', 'vhost', 'user', 'password']);

        $client = null;
        try {
            $client = $this->getSyncConnect($rabbitmqConfig);
            $channel = $client->channel();
            $this->declare($channel, $routingOrQueue, $exchange, $exchangeType);
            $published = $channel->publish($data, $headers, $exchange, $routingOrQueue, $mandatory, $immediate);
        } catch (\Throwable $throwable) {
            $this->logger?->error('['.getmypid().']PUBLISH SYNC:'.$throwable->getMessage().PHP_EOL.$throwable->getTraceAsString(), [__CLASS__]);
        } finally {
            // 每次新建连接，用完后必须主动断开，避免 fd 泄漏
            isset($channel) && $channel->close();
            $client?->disconnect();
        }

        return $published ?? false;
    }
}
