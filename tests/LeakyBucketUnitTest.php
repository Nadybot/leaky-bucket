<?php declare(strict_types=1);

namespace Nadylib\LeakyBucket\Tests;

use Nadylib\LeakyBucket\LeakyBucket;
use PHPUnit\Framework\Attributes\{DataProvider, Small};
use PHPUnit\Framework\TestCase;

#[Small]
final class LeakyBucketUnitTest extends TestCase {
	/** @return array<string,array{0:array<string,int|float>}> */
	public static function getBadQueueParameters(): array {
		return [
			"NegativeBucketSize" => [["size" => -1, "refillDelay" => 1]],
			"ZeroBucketSize" => [["size" => 0, "refillDelay" => 1]],
			"NegativeRefillDelay" => [["size" => 1, "refillDelay" => -0.1]],
			"NegativeRefillAmount" => [["size" => 1, "refillDelay" => 1, "refillAmount" => -1]],
			"ZeroRefillAmount" => [["size" => 1, "refillDelay" => 1, "refillAmount" => 0]],
		];
	}

	/** @param array<string,int|float> $params */
	#[DataProvider('getBadQueueParameters')]
	public function testDisallowBadQueueParameters($params): void {
		$this->expectException(\InvalidArgumentException::class);
		new LeakyBucket(...$params);
	}

	public function testCannotTakeMoreThanSize(): void {
		$this->expectException(\InvalidArgumentException::class);
		$bucket = new LeakyBucket(size: 5, refillDelay: 1);
		$bucket->take(6);
	}

	public function testTakingReducesFill(): void {
		$bucket = new LeakyBucket(
			size: 5,
			refillDelay: 0.01,
		);
		$bucket->take();
		$this->assertSame(4, $bucket->getFill());
		$bucket->take(2);
		$this->assertSame(2, $bucket->getFill());
	}

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
}
