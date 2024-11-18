<?php

namespace Roiwk\Rabbitmq;

use Psr\Log\LoggerInterface;

abstract class AbstractConsumer implements Consumable
{
    protected string $exchange = '';
    protected string $exchangeType = '';

    protected string $queue = '';

    // topic exchange - routingKeys
    protected array $routingKeys = [];

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
        'auto_delete' => false,
        'exclusive' => false,
        'nowait' => false,
        'arguments' => [],
    ];

    protected array $queueBindDefault = [
        'nowait' => false,
        'arguments' => [],
    ];

    protected array $consumeDefault = [
        'consumerTag' => '',
        'noLocal' => false,
        'noAck' => false,
        'exclusive' => false,
        'nowait' => false,
        'arguments' => [],
    ];

    protected array $qosDefault = [
        'prefetch_size' => 0,
        'prefetch_count' => 1,
    ];

    protected array $exchangeDeclare = [];
    protected array $queueDeclare = [];
    protected array $queueBind = [];
    protected array $consume = [];
    protected array $qos = [];

    protected bool $async = true;

    public function __construct(
        protected array $rabbitmqConfig,
        protected ?LoggerInterface $logger = null,
    ){

    }

    public function getClient()
    {
        $initProperty = [
            'exchangeDeclare' => 'exchangeDeclareDefault',
            'queueDeclare' => 'queueDeclareDefault',
            'queueBind' => 'queueBindDefault',
            'consume' => 'consumeDefault',
            'qos' => 'qosDefault',
        ];

        array_walk($initProperty, function ($default, $current) {
            if (empty($this->{$current})) {
                $this->{$current} = $this->{$default};
            } else {
                $this->{$current} = array_replace_recursive($this->{$default}, $this->{$current});
            }
        });

        return new Client(
            $this->rabbitmqConfig, $this->logger, $this->exchange, $this->exchangeType,
            $this->queue, $this->routingKeys, $this->exchangeDeclare, $this->queueDeclare,
            $this->queueBind, $this->consume, $this->qos
        );
    }

    public function onWorkerStart($worker): void
    {
        $client = $this->getClient();
        if (is_a(static::class, AbstractConsumer::class, true) || is_subclass_of(static::class, Consumable::class)) {
            if ($this->async) {
                $client->asyncProcess([$this, 'consume']);
            } else {
                $client->syncProcess([$this, 'consume']);
            }
        } else {
            return;
        }
    }
}
