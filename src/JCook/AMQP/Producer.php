<?php

namespace JCook\AMQP;

use AMQPQExchange;
use AMQPQExchangeException;
use React\EventLoop\LoopInterface;
use Evenement\EventEmitter;

/**
 * Class to publish messages to an AMQP exchange
 *
 * @package AMQP
 * @author  Jeremy Cook <jeremycook0@gmail.com>
 */
class Producer extends EventEmitter
{
    /**
     * AMQP message exchange to send messages to
     * @var AMQPExchange
     */
    protected $exchange;

    /**
     * Event loop
     * @var React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * Flag to indicate if this listener is closed
     * @var bool
     */
    protected $closed = false;

    /**
     * Collection of messages waiting to be sent
     * @var array
     */
    protected $messages = array();

    /**
     * Constructor. Stores the message queue and the event loop for use.
     * @param AMQPExchange                  $exchange Message queue
     * @param React\EventLoop\LoopInterface $loop     Event loop
     * @param float                         $interval Interval to run loop to send messages
     */
    public function __construct(AMQPExchange $exchange, LoopInterface $loop, $interval, $interval)
    {
        $this->exchange = $exchange;
        $this->loop     = $loop;
        $this->loop->addPeriodicTimer($interval, $this);
    }

    /**
     * Method to publish a message to an AMQP exchange. Has the same method
     * signature as the exchange objects publish method.
     * @param string $message    Message
     * @param string $routingKey Routing key
     * @param int    $flags      Flags
     * @param array  $attributes Attributes
     */
    public function publish($message, $routingKey, $flags = null, $attributes = [])
    {
        $this->messages[] = [
            'message'    => $message,
            'routingKey' => $routingKey,
            'flags'      => $flags,
            'attributes' => $attributes
        ];
    }

    /**
     * Callback to dispatch on the loop timer.
     */
    public function __invoke()
    {
        foreach ($this->messages as $key => $message) {
            try {
                $this->exchange->publish($message['message'], $message['routingKey'], $message['flags'], $message['attributes']);
                unset($this->messages[$key]);
                $this->emit('AMQPWrite', $message);
            } catch (AMQPExchangeException $e) {
                $this->emit('AMQPWriteError', [$e]);
            }
        }
    }

    /**
     * Allows calls to unknown methods to be passed through to the exchange
     * stored.
     * @param string $method Method name
     * @param mixed  $args   Args to pass
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->exchange, $method], $args);
    }

    /**
     * Method to call when stopping listening to messages
     */
    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->emit('end', [$this]);
        $this->loop->cancelTimer(spl_object_hash($this));
        $this->removeAllListeners();
        unset($this->queue);
        $this->closed = true;
    }
}
