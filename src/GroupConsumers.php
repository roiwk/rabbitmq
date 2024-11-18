<?php

namespace Roiwk\Rabbitmq;

use Psr\Log\LoggerInterface;

/**
 * 分组消费.(模仿webman-redis queue).
 */
class GroupConsumers
{
    public function __construct(
        protected $consumer_dir = '',
        protected array $rabbitmqConfig,
        protected ?LoggerInterface $logger = null,
    ) {
    }

    public function onWorkerStart($worker): void
    {
        if (!is_dir($this->consumer_dir)) {
            echo "Consumer directory {$this->consumer_dir} not exists\r\n";

            return;
        }
        $dir_iterator = new \RecursiveDirectoryIterator($this->consumer_dir);
        $iterator = new \RecursiveIteratorIterator($dir_iterator);
        foreach ($iterator as $file) {
            if (is_dir($file)) {
                continue;
            }
            $fileinfo = new \SplFileInfo($file);
            $ext = $fileinfo->getExtension();
            if ('php' === $ext) {
                $class = str_replace('/', '\\', substr(substr($file, strlen(base_path())), 0, -4));
                if (!is_a($class, AbstractConsumer::class, true)) {
                    continue;
                }

                if (class_exists('support\Container', false)) {
                    $consumer = \support\Container::make($class, [
                        'rabbitmqConfig' => $this->rabbitmqConfig, 'logger' => $this->logger
                    ]);
                } else {
                    $consumer = new $class($this->rabbitmqConfig, $this->logger);
                }
                $consumer->onWorkerStart($worker);
            }
        }
    }
}
