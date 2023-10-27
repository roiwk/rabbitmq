<?php

namespace Roiwk\Rabbitmq;

use Bunny\Channel;
use Bunny\Message;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Workerman\RabbitMQ\Client as AsyncClient;

class Client
{
    public function __construct(
        protected array $config,
        protected ?LoggerInterface $logger = null,
        protected string $exchange = '',
        protected string $exchangeType = '',
        protected string $queue = '',
        protected array $routingKeys = [],
        protected array $exchangeDeclare = [],
        protected array $queueDeclare = [],
        protected array $queueBind = [],
        protected array $consume = [],
        protected array $qos = [])
    {
    }

    public function getErrorCallback(): \Closure
    {
        if (!empty($this->config['error_callback'])) {
            return $this->config['error_callback'];
        } else {
            return function (\Throwable $throwable) {
                $this->logger?->error('['.getmypid().']:'.$throwable->getMessage().PHP_EOL, [$throwable->getTraceAsString()]);
            };
        }
    }

    public function asyncDeclare(Channel $channel)
    {
        if (empty($this->exchangeType)) {
            return $channel->queueDeclare(
                $this->queue, $this->queueDeclare['passive'],
                $this->queueDeclare['durable'], $this->queueDeclare['exclusive'], $this->queueDeclare['auto_delete'],
                $this->queueDeclare['nowait'], $this->queueDeclare['arguments']
            )
            ->then(function () use ($channel) {
                return $channel;
            });
        } else {
            return $channel->exchangeDeclare($this->exchange, $this->exchangeType,
                $this->exchangeDeclare['passive'], $this->exchangeDeclare['durable'],
                $this->exchangeDeclare['auto_delete'], $this->exchangeDeclare['internal'],
                $this->exchangeDeclare['nowait'], $this->exchangeDeclare['arguments']
            )
                ->then(function () use ($channel) {
                    return $channel->queueDeclare($this->queue, $this->queueDeclare['passive'],
                        $this->queueDeclare['durable'], $this->queueDeclare['exclusive'], $this->queueDeclare['auto_delete'],
                        $this->queueDeclare['nowait'], $this->queueDeclare['arguments']
                    )
                        ->then(function () use ($channel) {
                            $promises = [];

                            if (empty($this->routingKeys)) {
                                $promises[] = $channel->queueBind($this->queue, $this->exchange, '', $this->queueBind['nowait'], $this->queueBind['arguments']);
                            } else {
                                foreach ($this->routingKeys as $binding_key) {
                                    $promises[] = $channel->queueBind($this->queue, $this->exchange, $binding_key, $this->queueBind['nowait'], $this->queueBind['arguments']);
                                }
                            }

                            return \React\Promise\all($promises)->then(function () use ($channel) {
                                return $channel;
                            });
                        });
                });
        }
    }

    public function asyncProcess(callable $consumer)
    {
        $reject = $this->getErrorCallback();

        (new AsyncClient($this->config, $this->logger))->connect()
            ->then(function (AsyncClient $client) {
                return $client->channel();
            }, $reject)
            ->then(function (Channel $channel) {
                return $channel->qos($this->qos['prefetch_size'] ?? 0, $this->qos['prefetch_count'] ?? 0)
                    ->then(function () use ($channel) {
                        return $this->asyncDeclare($channel)->then(function () use ($channel) {
                            return $channel;
                        });
                    });
            }, $reject)
            ->then(function (Channel $channel) use ($reject, $consumer) {
                $this->logger?->debug('Waiting:['.getmypid().'] Waiting for messages.', []);
                $channel->consume(
                    function (Message $message, Channel $channel, AsyncClient $client) use ($reject, $consumer) {
                        $this->logger?->info('Received:['.getmypid().']: '.$message->content, [$message]);

                        try {
                            call_user_func_array($consumer, [$message, $channel, $client]);
                        } catch (\Throwable $throw) {
                            if (!$this->consume['noAck']) {
                                $channel->nack($message);
                            }
                            $reject($throw);
                        }

                        return;
                    },
                    $this->queue,
                    $this->consume['consumerTag'],
                    $this->consume['noLocal'],
                    $this->consume['noAck'],
                    $this->consume['exclusive'],
                    $this->consume['nowait'],
                    $this->consume['arguments'],
                );
            }, $reject);
    }

    public function syncDeclare(Channel $channel)
    {
        if (empty($this->exchangeType)) {
            $channel->queueDeclare(
                $this->queue, $this->queueDeclare['passive'],
                $this->queueDeclare['durable'], $this->queueDeclare['exclusive'], $this->queueDeclare['auto_delete'],
                $this->queueDeclare['nowait'], $this->queueDeclare['arguments']
            );
        } else {
            $channel->exchangeDeclare($this->exchange, $this->exchangeType,
                $this->exchangeDeclare['passive'], $this->exchangeDeclare['durable'],
                $this->exchangeDeclare['auto_delete'], $this->exchangeDeclare['internal'],
                $this->exchangeDeclare['nowait'], $this->exchangeDeclare['arguments']
            );
            $channel->queueDeclare($this->queue, $this->queueDeclare['passive'],
                $this->queueDeclare['durable'], $this->queueDeclare['exclusive'], $this->queueDeclare['auto_delete'],
                $this->queueDeclare['nowait'], $this->queueDeclare['arguments']
            );
            if (empty($this->routingKeys)) {
                $channel->queueBind($this->queue, $this->exchange, '', $this->queueBind['nowait'], $this->queueBind['arguments']);
            } else {
                foreach ($this->routingKeys as $binding_key) {
                    $channel->queueBind($this->queue, $this->exchange, $binding_key, $this->queueBind['nowait'], $this->queueBind['arguments']);
                }
            }
        }
    }

    public function syncProcess(callable $consumer)
    {
        try {
            $reject = $this->getErrorCallback();
            $rabbitmqConfig = Arr::only($this->config, ['host', 'port', 'vhost', 'user', 'password']);
            $client = (new \Bunny\Client($rabbitmqConfig))->connect();
            $channel = $client->channel();
            $this->syncDeclare($channel);
            $channel->consume(
                function (Message $message, Channel $channel, \Bunny\Client $client) use ($consumer) {
                    $this->logger?->info('Received:['.getmypid().']: '.$message->content, [$message]);
                    try {
                        call_user_func_array($consumer, [$message, $channel, $client]);
                    } catch (\Throwable $throw) {
                        if (!$this->consume['noAck']) {
                            $channel->nack($message);
                        }
                        throw $throw;
                    }
                },
                $this->queue,
                $this->consume['consumerTag'],
                $this->consume['noLocal'],
                $this->consume['noAck'],
                $this->consume['exclusive'],
                $this->consume['nowait'],
                $this->consume['arguments'],
            );
            $client->run();
        } catch (\Throwable $throwable) {
            $reject($throwable);
        }
    }
}
