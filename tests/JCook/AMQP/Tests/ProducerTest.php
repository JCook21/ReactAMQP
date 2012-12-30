<?php

namespace JCook\AMQP\Tests;

use PHPUnit_Framework_TestCase;
use JCook\AMQP\Producer;
use AMQPExchangeException;

/**
 * Test case for the producer class
 *
 * @package AMQP
 * @author  Jeremy Cook <jeremycook0@gmail.com>
 */
class ProducerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Mock exchange object
     * @var AMQPExchange
     */
    protected $exchange;

    /**
     * Mock loop object
     * @var React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * Counter to keep track of the number of times this object is called as a
     * callback.
     * @var int
     */
    protected $counter = 0;

    /**
     * Bootstrap the test case
     */
    protected function setUp()
    {
        $this->exchange = $this->getMockBuilder('AMQPExchange')
            ->disableOriginalConstructor()
            ->getMock();
        $this->loop = $this->getMock('React\\EventLoop\\LoopInterface');
    }

    /**
     * Reset the counter to 0 after each test method has run
     */
    protected function tearDown()
    {
        $this->counter = 0;
    }

    /**
     * Allows the test class to be used as a callback by the producer. Simply
     * counts the number of times the invoke method is called.
     */
    public function __invoke()
    {
        $this->counter++;
    }

    /**
     * Tests the constructor for the producer
     * @param float $interval
     *
     * @dataProvider IntervalSupplier
     */
    public function test__construct($interval)
    {
        $this->loop->expects($this->once())
            ->method('addPeriodicTimer')
            ->with($this->identicalTo($interval), $this->isInstanceOf('JCook\\AMQP\\Producer'));
        $producer = new Producer($this->exchange, $this->loop, $interval);
        $this->assertAttributeSame($this->loop, 'loop', $producer);
        $this->assertAttributeSame($this->exchange, 'exchange', $producer);
    }

    /**
     * Tests the publish method of the producer.
     * @param string $message    Message
     * @param string $routingKey Routing key
     * @param int    $flags      Flags, if any
     * @param array  $attributes Attributes
     *
     * @dataProvider MessageProvider
     */
    public function testPublish($message, $routingKey, $flags, array $attributes)
    {
        $producer = new Producer($this->exchange, $this->loop, 1);
        $this->assertAttributeCount(0, 'messages', $producer);
        $producer->publish($message, $routingKey, $flags, $attributes);
        $this->assertAttributeCount(1, 'messages', $producer);
    }

    /**
     * Asserts that messages stored in the object can be sent.
     * @param array $messages
     *
     * @depends testPublish
     * @dataProvider MessagesProvider
     */
    public function testSendingMessages(array $messages)
    {
        $producer = new Producer($this->exchange, $this->loop, 1);
        $producer->on('produce', $this);
        foreach ($messages as $message) {
            call_user_func_array(array($producer, 'publish'), $message);
        }
        $this->exchange->expects($this->exactly(count($messages)))
            ->method('publish');
        $this->assertAttributeCount(count($messages), 'messages', $producer);
        $producer();
        $this->assertSame(count($messages), $this->counter);
        $this->assertAttributeCount(0, 'messages', $producer);
    }

    /**
     * Tests the behaviour of the producer when an exception is raised by the
     * exchange.
     * @param array $messages
     *
     * @depends testPublish
     * @dataProvider MessagesProvider
     */
    public function testSendingMessagesWithError(array $messages)
    {
        $producer = new Producer($this->exchange, $this->loop, 1);
        $producer->on('error', $this);
        foreach ($messages as $message) {
            call_user_func_array(array($producer, 'publish'), $message);
        }
        $this->exchange->expects($this->exactly(count($messages)))
            ->method('publish')
            ->will($this->throwException(new AMQPExchangeException));
        $producer();
        $this->assertSame(count($messages), $this->counter);
        $this->assertAttributeCount(count($messages), 'messages', $producer);
    }

    /**
     * Asserts that calls to unknown methods are proxied through to the
     * exchange object.
     * @param string $method Method name
     * @param string $arg    Arg to pass
     *
     * @dataProvider CallProvider
     */
    public function test__call($method, $arg)
    {
        $this->exchange->expects($this->once())
            ->method($method)
            ->with($arg);
        $producer = new Producer($this->exchange, $this->loop, 1);
        $producer->$method($arg);
    }

    /**
     * Tests the close method
     */
    public function testClose()
    {
        $producer = new Producer($this->exchange, $this->loop, 1);
        $producer->on('end', $this);
        $this->loop->expects($this->once())
            ->method('cancelTimer')
            ->with(spl_object_hash($producer));
        $producer->close();
        $this->assertAttributeSame(true, 'closed', $producer);
        $this->assertAttributeSame(null, 'exchange', $producer);
        $this->assertSame(1, $this->counter);
    }

    /**
     * Data supplier with intervals for the constructor of the producer
     *
     * @return array
     */
    public static function IntervalSupplier()
    {
        return array(
            [1],
            [2.4],
            [0.05]
        );
    }

    /**
     * Data provider with message values
     * @return array
     */
    public static function MessageProvider()
    {
        return array(
            ['foo', 'bar', 1 & 1, []],
            ['bar', 'baz', 1 & 0, ['foo' => 'bar']]
        );
    }

    /**
     * Data provider with multiple messages to send.
     * @return array
     */
    public static function MessagesProvider()
    {
        return array(
            [[
            ['foo', 'bar', 1 & 1, []],
            ['bar', 'baz', 1 & 0, ['foo' => 'bar']]
            ]]
        );
    }

    /**
     * Data provider for testing __call on the producer.
     * @return array
     */
    public static function CallProvider()
    {
        return array(
            ['setName', 'foo'],
            ['setType', 'bar']
        );
    }
}
