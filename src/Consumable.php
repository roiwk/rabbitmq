<?php

namespace Roiwk\Rabbitmq;

use Bunny\AbstractClient;
use Bunny\Channel;
use Bunny\Message;

interface Consumable
{
    public function consume(Message $message, Channel $channel, AbstractClient $client);
}
