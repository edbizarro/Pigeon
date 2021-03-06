<?php

namespace Convenia\Pigeon\MessageProcessor;

use Closure;
use Convenia\Pigeon\Drivers\DriverContract;
use Convenia\Pigeon\Resolver\Resolver;
use Exception;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

class MessageProcessor implements MessageProcessorContract
{
    protected $callback;
    protected $fallback;
    protected $driver;

    public function __construct(DriverContract $driver, Closure $callback, Closure $fallback = null)
    {
        $this->driver = $driver;
        $this->callback = $callback;
        $this->fallback = $fallback;
    }

    public function process(AMQPMessage $message)
    {
        try {
            $this->callUserCallback($message);
        } catch (Exception $e) {
            $this->callUserFallback($e, $message);
        }
    }

    private function callUserCallback($message)
    {
        $data = json_decode($message->body, true);

        call_user_func($this->callback, $data, new Resolver($message));
    }

    private function callUserFallback(Exception $e, $message)
    {
        $resolver = new Resolver($message);
        if (! $this->fallback) {
            return $this->defaultFallback($e, $message, $resolver);
        }
        call_user_func($this->fallback, $e, $message, $resolver);
    }

    private function defaultFallback(Exception $e, $message, $resolver)
    {
        Log::error($e->getMessage(), [
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'tracing'  => $e->getTraceAsString(),
            'previous' => $e->getPrevious(),
            'message'  => json_decode($message->body, true),
        ]);

        switch (config('pigeon.consumer.on_failure')) {
            case 'ack':
                $resolver->ack();
                break;
            case 'reject':
                $resolver->reject(false);
                break;
            default:
                throw $e;
        }
    }
}
