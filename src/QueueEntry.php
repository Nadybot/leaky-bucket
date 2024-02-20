<?php declare(strict_types=1);

namespace Nadylib\LeakyBucket;

use Revolt\EventLoop\Suspension;

class QueueEntry {
	/** @param Suspension<null> $suspension */
	public function __construct(
		public readonly Suspension $suspension,
		public readonly int $amount
	) {
	}
}
