<?php declare(strict_types=1);

namespace Nadylib\LeakyBucket\Tests;

use Nadylib\LeakyBucket\LeakyBucket;
use PHPUnit\Framework\TestCase;

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
}
