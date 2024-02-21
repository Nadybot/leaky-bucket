# leaky-bucket

[![PHP Tests passing or not](https://github.com/nadybot/leaky-bucket/actions/workflows/php.yml/badge.svg)](https://github.com/Nadybot/leaky-bucket/actions/workflows/php.yml)

An async Leaky Bucket implementation using the Revolt EventLoop

## Usage

```php
use Nadylib\LeakyBucket\LeakyBucket;

$bucket = new Nadylib\LeakyBucket\LeakyBucket(
	size: 5,
	refillDelay: 1.0,
	refillAmount: 1
);
```

There is only 1 function of interest: `LeakyBucket::take(<amount>)`:

```php
use Nadylib\LeakyBucket\LeakyBucket;

$bucket = new Nadylib\LeakyBucket\LeakyBucket(
	size: 5,
	refillDelay: 1.0,
	refillAmount: 1
);
for ($i = 1; $i <= 7; $i++) {
	$bucket->take(1);
	$time = (new DateTimeImmutable())->format("H:i:s.v");
	echo("[{$time}] Taken.\n");
}
```

The first 5 takes will take immediately, while 6 and 7 take 1s each. There is no sleep involved, just async fibers, so this is absolutely safe to use.

```php
use Nadylib\LeakyBucket\LeakyBucket;
use Revolt\EventLoop;

$bucket = new Nadylib\LeakyBucket\LeakyBucket(
	size: 5,
	refillDelay: 1.0,
	refillAmount: 1
);
for ($i = 1; $i <= 7; $i++) {
	$bucket->take(callback: function() {
		$time = (new DateTimeImmutable())->format("H:i:s.v");
		echo("[{$time}] Taken.\n");
	});
}
EventLoop::run();
```

This will behave exactly the same, but the call to `$bucket->take()` will always return immediately.

When using the callback version, make sure to call `EventLoop::run()` at the end, because the main fiber/thread is never suspended, but always returns immediately, so you have to make sure the event loop finishes its work. In a real world environment, this will not be necessary, because your code will run in a global event loop anyway.
