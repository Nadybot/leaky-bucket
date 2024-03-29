<?php declare(strict_types=1);

namespace Nadylib\LeakyBucket\Tests;

use Nadylib\LeakyBucket\LeakyBucket;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

#[Medium]
final class LeakyBucketFunctionalTest extends TestCase {
	public function testPreciseDelayOnRefill(): void {
		$bucket = new LeakyBucket(
			size: 5,
			refillDelay: 0.2,
		);
		$start = microtime(true);
		for ($i = 1; $i <= 7; $i++) {
			$bucket->take();
		}
		$end = microtime(true);
		$this->assertLessThan(0.5, $end-$start);
		$this->assertGreaterThanOrEqual(0.4, $end-$start);
	}

	public function testPreciseDelayOnRefillWithCallback(): void {
		$bucket = new LeakyBucket(
			size: 5,
			refillDelay: 0.2,
		);
		$callbacks = 0;
		$times = [];
		for ($i = 1; $i <= 8; $i++) {
			$start = microtime(true);
			$bucket->take(callback: function () use (&$callbacks, &$times, $start) {
				$callbacks++;
				$end = microtime(true);
				$times []= round($end-$start, 1);
			});
		}
		EventLoop::run();
		$this->assertSame(8, $callbacks);
		$this->assertSame($times, [0.0, 0.0, 0.0, 0.0, 0.0, 0.2, 0.4, 0.6]);
	}

	public function testSleepingRefillsBucket(): void {
		$bucket = new LeakyBucket(
			size: 5,
			refillDelay: 0.1,
		);
		$callbacks = 0;
		$times = [];
		for ($i = 1; $i <= 5; $i++) {
			$start = microtime(true);
			$bucket->take(callback: function () use (&$callbacks, &$times, $start) {
				$callbacks++;
				$end = microtime(true);
				$times []= round($end-$start, 1);
			});
		}
		$this->assertSame(0, $bucket->getFill());
		$suspension = EventLoop::getSuspension();
		EventLoop::delay(0.32, fn () => $suspension->resume());
		$suspension->suspend();
		$this->assertSame(3, $bucket->getFill());
		EventLoop::run();
	}
}
