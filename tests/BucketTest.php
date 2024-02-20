<?php declare(strict_types=1);

namespace Nadylib\LeakyBucket\Tests;

use Nadylib\LeakyBucket\LeakyBucket;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

final class BucketTest extends TestCase {
	public function testNoDelayOnFull(): void {
		$bucket = new LeakyBucket(
			size: 5,
			refillDelay: 2.0,
		);
		$start = microtime(true);
		for ($i = 1; $i <= 5; $i++) {
			$bucket->take();
		}
		$end = microtime(true);
		$this->assertLessThan(0.1, $end-$start);
	}

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

	public function testNoDelayOnFullWithCallback(): void {
		$bucket = new LeakyBucket(
			size: 5,
			refillDelay: 2.0,
		);
		$callbacks = 0;
		$start = microtime(true);
		for ($i = 1; $i <= 5; $i++) {
			$bucket->take(callback: function () use (&$callbacks) {
				$callbacks++;
			});
		}
		$end = microtime(true);
		$this->assertSame(5, $callbacks);
		$this->assertLessThan(0.1, $end-$start);
	}

	public function testPreciseDelayOnRefillWithCallback(): void {
		$bucket = new LeakyBucket(
			size: 5,
			refillDelay: 0.2,
		);
		$callbacks = 0;
		$start = microtime(true);
		for ($i = 1; $i <= 7; $i++) {
			$bucket->take(callback: function () use (&$callbacks) {
				$callbacks++;
			});
		}
		EventLoop::run();
		$end = microtime(true);
		$this->assertSame(7, $callbacks);
		$this->assertLessThan(1.5, $end-$start);
		$this->assertGreaterThanOrEqual(1.4, $end-$start);
	}
}
