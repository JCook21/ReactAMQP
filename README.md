# ReactAMQP

Basic AMQP bindings for [React PHP](https://github.com/reactphp).

## Install
This library requires PHP 5.4 and the [PECL AMQP extension](http://pecl.php.net/package/amqp). The best way to install this library is [through composer](http://getcomposer.org).

```JSON
{
	"require": {
		"jcook/react-amqp": "dev-master"
	}
}
```

## Usage
This library provides two classes, an AMQP Consumer and Producer. Both classes work with a periodic timer and you supply the timer interval as an argument to the constructor.

### Consumer
The consumer class allows you to receive messages from an AMQP broker and to dispatch a callback whenever one is received. You can also supply a number of messages to consume in one go, making sure that your event loop isn't perpetually stuck consuming messages from a broker. The callback you supply must accept an AMQPEnvelope as the first argument and an optional AMQPQueue as the second.

```php
<?php
// Connect to an AMQP broker
$cnn = new AMQPConnection();
$cnn->connect();

// Create a channel
$ch = new AMQPChannel($cnn);

// Create a new queue
$queue = new AMQPQueue($ch);
$queue->setName('queue1');
$queue->declare();

// Create an event loop
$loop = React\EventLoop\Factory::create();

// Create a consumer that will check for messages every half a second and consume up to 10 at a time.
$consumer = new JCook\ReactAMQP\Consumer($queue, $loop, 0.5, 10);
$consumer->on('consume', function(AMQPEnvelope $envelope, AMQPQueue $queue){
	//Process the message here
});
$loop->run();
```

### Producer
The producer class allows you to send messages to an AMQP exchange. Messages are stored in the producer class and sent based on the timer interval passed to the constructor. The producer has a publish method that has exactly the same method signature as the AMQPExchange's publish method. When a message is successfully sent a 'produce' event is emitted that you can bind a callback to. This is passed an array containing all of the message parameters sent. If an AMQPExchangeException is thrown, meaning the message could not be sent, an 'error' event is emitted that you can bind a callback to. This will be passed the AMQPExchangeException object for you to handle.

```php
<?php
// Connect to an AMQP broker
$cnn = new AMQPConnection();
$cnn->connect();

// Create a channel
$ch = new AMQPChannel($cnn);

// Declare a new exchange
$ex = new AMQPExchange($ch);
$ex->setName('exchange1');
$ex->declare();

// Create an event loop
$loop = React\EventLoop\Factory::create();

// Create a producer that will send any waiting messages every half a second.
$producer = new JCook\ReactAMQP\Producer($ex, $loop, 0.5);

// Add a callback that's called every time a message is successfully sent.
$producer->on('produce', function(array $message) {
	// $message is an array containing keys 'message', 'routingKey', 'flags' and 'attributes'
});

$producer->on('error', function(AMQPExchangeException $e) {
	// Handle any exceptions here.
});

$i = 0;

$loop->addPeriodicTimer(1, function() use(&$i, $producer) {
	$i++;
	echo "Sending $i\n";
	$producer->publish($i, 'routing.key');
});

$loop->run();
```

## Limitations
As the PECL AMQP extension does not provide any way to 'listen' to the underlying AMQP sockets this library has to use periodic timers. This is not non-blocking IO and won't perform as well as a result. These classes should work well enough though until a better alternative is available.

## TODO
- Add support for the [php-amqplib](https://github.com/videlalvaro/php-amqplib).
- Add checks to the consumer and producer classes to check if the object has been closed.
