# RabbitMQ
rabbitmq async(workerman) and sync PHP Client, Producers, Consumers

rabbitmq 是一个异步（workerman）和同步的PHP客户端，用于异步（workerman）和同步的生产者和消费者。


# Dependencies 依赖

php >= 8.0

# Install 安装

```sh
composer require roiwk/rabbitmq
```

# Usage 使用

[All demo](./example/)

## Config Demo

```php
// 配置格式
$config = [
    'host' => '127.0.0.1',
    'port' => 5672,
    'vhost' => '/',
    'mechanism' => 'AMQPLAIN',
    'user' => 'username',
    'password' => 'password',
    'timeout' => 10,
    'heartbeat' => 60,
    'heartbeat_callback' => function(){},
    'error_callback'     => null,
];
```

## Publisher Demo
```php
// 同步发布者  sync Publisher
Roiwk\Rabbitmq\Producer::connect($config)->publishSync('Hello World!', '', '', 'hello');

// 异步发布者  async Publisher(workerman)

use Workerman\Worker;

$worker = new Worker();
$worker->onWorkerStart = function() use($config) {
    Roiwk\Rabbitmq\Producer::connect($config)->publishAsync('Hello World!', '', '', 'hello');
};
Worker::runAll();
```


## Consumer Demo

```php
// 同步消费者  sync Consumer

use Bunny\AbstractClient;
use Roiwk\Rabbitmq\AbstractConsumer;

// style 1:
$client = new Roiwk\Rabbitmq\Client($config, null, '', '', 'hello');
$client->syncProcess(function(Message $message, Channel $channel, AbstractClient $client){
    echo " [x] Received ", $message->content, "\n";
    $channel->ack();
});

// style 2:
$consumer = new class ($config) extends AbstractConsumer {
    protected bool $async = false;
    protected string $queue = 'hello';
    protected array $consume = [
        'noAck' => true,
    ];
    public function consume(Message $message, Channel $channel, AbstractClient $client)
    {
        echo " [x] Received ", $message->content, "\n";
    }
};
$consumer->onWorkerStart(null);

```

```php
// 异步消费者  async Consumer(workerman)

use Workerman\Worker;
use Bunny\AbstractClient;
use Roiwk\Rabbitmq\AbstractConsumer;

$worker = new Worker();

$consumer = new class ($config) extends AbstractConsumer {

    protected bool $async = true;

    protected string $queue = 'hello';

    protected array $consume = [
        'noAck' => true,
    ];

    public function consume(Message $message, Channel $channel, AbstractClient $client)
    {
        echo " [x] Received ", $message->content, "\n";
    }
};

$worker->onWorkerStart = [$consumer, 'onWorkerStart'];
Worker::runAll();

```

# Advanced 高级用法

## webman中自定义进程--消费者

1.process.php
```php
'hello-rabbitmq' => [
    'handler' => app\queue\rabbitmq\Hello::class,
    'count'   => 1,
    'constructor' => [
        'rabbitmqConfig' => $config,
        //'logger' => Log::channel('hello'),
    ],
]
```
2.app\queue\rabbitmq\Hello.php

```php
namespace app\queue\rabbitmq;

use Roiwk\Rabbitmq\AbstractConsumer;
use Roiwk\Rabbitmq\Producer;
use Bunny\Channel;
use Bunny\Message;
use Bunny\AbstractClient;

class Hello extends AbstractConsumer
{
    protected bool $async = true;

    protected string $queue = 'hello';

    protected array $consume = [
        'noAck' => true,
    ];

    public function consume(Message $message, Channel $channel, AbstractClient $client)
    {
        echo " [x] Received ", $message->content, "\n";
    }
}

```

## webman中自定义进程--分组消费者
  类似webman-queue插件, 分组将消费者放同一个文件夹下, 使用同一个worker, 多个进程数处理  
1.process.php
```php
'hello-rabbitmq' => [
    'handler' => Roiwk\Rabbitmq\GroupConsumers::class,
    'count'   => 2,
    'constructor' => [
        'consumer_dir' => app_path().'/queue/rabbimq',
        'rabbitmqConfig' => $config,
        //'logger' => Log::channel('hello'),
    ],
]
```
2.在 ```app_path().'/queue/rabbimq'``` 目录下创建php文件, 继承```Roiwk\Rabbitmq\AbstractConsumer```即可, 同上```app\queue\rabbitmq\Hello.php```





# Tips

> 1. !!!  此库异步仅支持在workamn环境中, 同步环境都支持. 别的环境,如需异步, 请使用[bunny](https://packagist.org/packages/bunny/bunny)的客户端


