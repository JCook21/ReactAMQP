<?php

namespace JCook\ReactAMQP\Tests;

use PHPUnit_Framework_TestCase;
use JCook\ReactAMQP\Consumer;

/**
 * Test case for the consumer class
 *
 * @package AMQP
 * @author  Jeremy Cook <jeremycook0@gmail.com>
 */
class ConsumerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Mock queue object
     * @var AMQPQueue
     */
    protected $queue;

    /**
     * Mock loop object
     * @var React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * Counter to test the number of invokations of an observed object
     * @var int
     */
    protected $counter = 0;

    /**
     * Bootstrap the test case
     */
    protected function setUp()
    {
        $this->queue = $this->getMockBuilder('AMQPQueue')
            ->disableOriginalConstructor()
            ->getMock();
        $this->loop = $this->getMockBuilder('React\\EventLoop\\LoopInterface')
            ->getMock();
    }

    /**
     * Tear down resets the counter after each test method has run
     */
    protected function tearDown()
    {
        $this->counter = 0;
    }

    /**
     * Allows the test class to be used as a callback by the consumer. Simply
     * counts the number of times the invoke method is called.
     */
    public function __invoke()
    {
        $this->counter++;
    }

    /**
     * Tests the constructor for the consumer class
     * @param int   $interval Interval for the loop
     * @param mixed $max      Max number of messages to consume
     *
     * @dataProvider IntervalMaxSupplier
     */
    public function test__construct($interval, $max)
    {
        $this->loop->expects($this->once())
            ->method('addPeriodicTimer')
            ->with($this->identicalTo($interval), $this->isInstanceOf('JCook\ReactAMQP\Consumer'));
        $consumer = new Consumer($this->queue, $this->loop, $interval, $max);
        $this->assertAttributeSame($this->queue, 'queue', $consumer);
        $this->assertAttributeSame($this->loop, 'loop', $consumer);
        $this->assertAttributeSame($max, 'max', $consumer);
    }

    /**
     * Basic test case that asserts that messages can be consumed from the
     * queue
     */
    public function testConsumingMessages()
    {
        $this->queue->expects($this->exactly(4))
            ->method('get')
            ->will($this->onConsecutiveCalls('foo', 'bar', 'baz', false));
        $consumer = new Consumer($this->queue, $this->loop, 1);
        $consumer->on('consume', $this);
        $consumer();
        $this->assertSame(3, $this->counter);
    }

    /**
     * Asserts that supplying a value for the max number of messages to consume
     * results in the Consumer returning.
     * @param int $max
     *
     * @dataProvider MaxSupplier
     */
    public function testConsumingMessagesWithMaxCount($max)
    {
        $this->queue->expects($this->exactly($max))
            ->method('get')
            ->will($this->returnValue('foobar'));
        $consumer = new Consumer($this->queue, $this->loop, 1, $max);
        $consumer->on('consume', $this);
        $consumer();
        $this->assertSame($max, $this->counter);
    }

    /**
     * Asserts that calling unknown methods on the consumer object results in
     * these being passed through to the internal queue object
     * @param string $method Method name
     * @param string $arg    Argument to pass
     *
     * @dataProvider CallSupplier
     */
    public function test__call($method, $arg)
    {
        $this->queue->expects($this->once())
            ->method($method)
            ->with($this->identicalTo($arg));
        $consumer = new Consumer($this->queue, $this->loop, 1);
        $consumer->$method($arg);
    }

    /**
     * Tests the close method of the consumer
     */
    public function testClose()
    {
        $consumer = new Consumer($this->queue, $this->loop, 1);
        $consumer->on('end', $this);
        $this->loop->expects($this->once())
            ->method('cancelTimer')
            ->with($this->identicalTo(spl_object_hash($consumer)));
        $consumer->close();
        $this->assertAttributeSame(true, 'closed', $consumer);
        $this->assertAttributeSame(null, 'queue', $consumer);
        $this->assertSame(1, $this->counter);
    }

    /**
     * Asserts that an exception is thrown when trying to invoke a consumer
     * after closing it.
     *
     * @depends testClose
     * @expectedException BadMethodCallException
     */
    public function testInvokingConsumerAfterClosing()
    {
        $consumer = new Consumer($this->queue, $this->loop, 1);
        $consumer->close();
        $consumer();
    }

    /**
     * Data provider with interval and max iteration values
     * @return array
     */
    public static function IntervalMaxSupplier()
    {
        return array(
            [1, null],
            [1, 1],
            [0.05, 10],
        );
    }

    /**
     * Data provider with values for the max number of messages to consume
     * @return array
     */
    public static function MaxSupplier()
    {
        return array(
            [1],
            [10],
            [45]
        );
    }

    /**
     * Data provider with arguments for the test that tests the consumers
     * __call method
     * @return array
     */
    public static function CallSupplier()
    {
        return array(
            ['getArgument', 'foo'],
            ['nack', 'bar'],
            ['cancel', 'baz']
        );
    }
}
