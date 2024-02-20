<?php declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\{Level, Logger};
use Revolt\EventLoop;

function delay(float $timeout, Logger $logger): void {
	$suspension = EventLoop::getSuspension();
	$logger->debug("Sleeping for {$timeout}s");
	$callbackId = EventLoop::delay($timeout, static fn () => $suspension->resume());

	try {
		$suspension->suspend();
	} finally {
		EventLoop::cancel($callbackId);
	}
}


$logger = new Logger('mylogger');
$logger->pushHandler(new StreamHandler("php://stdout", Level::Debug));
$logger->pushProcessor(new PsrLogMessageProcessor(null, true));


$bucket = new Nadybot\LeakyBucket(size: 5, refillDelay: 1.0, refillAmount: 1, logger: $logger);

for ($i = 1; $i <= 15; $i++) {
	if (random_int(1, 3) === 1) {
		delay(2.0, $logger);
	}
	$amount = random_int(1, 2);
	$logger->debug("Taking #{i} for {amount}", ["i" => $i, "amount" => $amount]);
	$bucket->take($amount);
}

EventLoop::run();
