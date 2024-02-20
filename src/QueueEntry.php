<?php declare(strict_types=1);

namespace Nadylib\LeakyBucket;

use Closure;
use Revolt\EventLoop\Suspension;

class QueueEntry {
	/** @param Suspension<null> $callback */
	public function __construct(
		public readonly Closure|Suspension $callback,
		public readonly int $amount
	) {
	}
}
