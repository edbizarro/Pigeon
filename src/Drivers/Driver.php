<?php

namespace Convenia\Pigeon\Drivers;

use Exception;
use PhpAmqpLib\Wire\AMQPTable;
use Convenia\Pigeon\Consumer\Consumer;
use Illuminate\Foundation\Application;
use Convenia\Pigeon\Publisher\Publisher;
use PhpAmqpLib\Connection\AbstractConnection;
use Convenia\Pigeon\Consumer\ConsumerContract;
use Convenia\Pigeon\Publisher\PublisherContract;
use Convenia\Pigeon\Drivers\DriverContract as DriverContract;

abstract class Driver implements DriverContract
{
    public $app;

    /**
     * @var \PhpAmqpLib\Connection\AbstractConnection
     */
    protected $connection;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    abstract public function getConnection(): AbstractConnection;

    public function queue(string $name, array $properties = []): ConsumerContract
    {
        $this->getChannel()->queue_declare($name, false, true, false, false, false, $properties);

        return new Consumer($this->app, $this, $name);
    }

    public function exchange(string $name, string $type = 'direct'): PublisherContract
    {
        $this->getChannel()->exchange_declare($name, $type, true, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => 'dead.letter',
        ]));

        return new Publisher($this->app, $this, $name);
    }

    public function routing(string $name = null): PublisherContract
    {
        $exchange = $this->app['config']['pigeon.exchange'];
        $type = $this->app['config']['pigeon.exchange_type'];

        $this->getChannel()->exchange_declare($exchange, $type, true, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => 'dead.letter',
        ]));

        return (new Publisher($this->app, $this, $exchange))->routing($name);
    }
}
