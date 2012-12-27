<?php

namespace JCook\AMQP;

use AMQPQueue;
use React\EventLoop\LoopInterface;
use Evenement\EventEmitter;

/**
 * Class to listen to an AMQP queue and dispatch listeners when messages are
 * received.
 *
 * @package AMQP
 * @author  Jeremy Cook <jeremycook0@gmail.com>
 */
class Consumer extends EventEmitter
{
    /**
     * AMQP message queue to read messages from
     * @var AMQPQueue
     */
    protected $queue;

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
     * Constructor. Stores the message queue and the event loop for use.
     * @param AMQPQueue                     $queue    Message queue
     * @param React\EventLoop\LoopInterface $loop     Event loop
     * @param float                         $interval Interval to check for new messages
     */
    public function __construct(AMQPQueue $queue, LoopInterface $loop, $interval)
    {
        $this->queue = $queue;
        $this->loop  = $loop;
        $this->loop->addPeriodicTimer($interval, $this);
    }

    /**
     * Method to handle receiving an incoming message
     * @return void
     */
    public function __invoke()
    {
        while ($envelope = $this->queue->get()) {
            $this->emit('AMQPRead', [$envelope, $this->queue]);
        }
    }

    /**
     * Allows calls to unknown methods to be passed through to the queue
     * stored.
     * @param string $method Method name
     * @param mixed  $args   Args to pass
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->queue, $method], $args);
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
