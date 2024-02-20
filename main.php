<?php declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";

use Nadylib\LeakyBucket\LeakyBucket;
use Revolt\EventLoop;

$bucket = new LeakyBucket(
	size: 5,
	refillDelay: 1,
);
$callbacks = 0;
$start = microtime(true);
for ($i = 1; $i <= 7; $i++) {
	$bucket->take();
	echo("Taken\n");
	// $bucket->take(callback: function () use (&$callbacks) {
	// 	echo("In Callback\n");
	// 	$callbacks++;
	// });
}
EventLoop::run();
