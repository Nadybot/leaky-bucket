<?php declare(strict_types=1);

namespace Nadylib\LeakyBucket;

use Closure;
use Error;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

class LeakyBucket {
	protected int $fill = 0;

	/** @var QueueEntry[] */
	protected array $queue = [];

	protected ?string $cancellationToken = null;

	/**
	 * Create a new leaky bucket with the given parameters
	 *
	 * @param int                  $size         The total size of the bucket. How many tokens fit in
	 * @param float                $refillDelay  The delay in seconds for the bucket to be refilled
	 *                                           by $refillAmount
	 * @param int                  $refillAmount How many tokens to add to the bucket every $refillDelay
	 * @param null|int             $startFill    How much is in the bucket initially? NULL means it's full
	 * @param null|LoggerInterface $logger       An optional logger to pas
	 *
	 * @phpstan-param positive-int $size
	 * @phpstan-param positive-int $refillAmount
	 *
	 * @throws InvalidArgumentException
	 */
	final public function __construct(
		public readonly int $size,
		public readonly float $refillDelay,
		public readonly int $refillAmount=1,
		?int $startFill=null,
		protected ?LoggerInterface $logger=null,
	) {
		/** @phpstan-ignore-next-line */
		if ($size < 1) {
			throw new InvalidArgumentException(
				__CLASS__ ."::" . __FUNCTION__ . "(\$size) needs to be a positive integer."
			);
		}
		if ($refillDelay < 0) {
			throw new InvalidArgumentException(
				__CLASS__ ."::" . __FUNCTION__ . "(\$refillDelay) needs to be a non-negative float."
			);
		}

		/** @phpstan-ignore-next-line */
		if ($refillAmount < 1) {
			throw new InvalidArgumentException(
				__CLASS__ ."::" . __FUNCTION__ . "(\$refillAmount) needs to be a positive integer."
			);
		}
		$this->fill = $startFill ?? $this->size;
	}

	/** Get how much is currently in the bucket */
	public function getFill(): int {
		return $this->fill;
	}

	/** Check if we could take $amount without delay */
	public function canTake(int $amount=1): bool {
		return $this->fill < $amount;
	}

	/**
	 * Try to take out something from the bucket
	 *
	 * If there is currently at least $amount in the bucket, immediately return,
	 * optionally calling $callback if set. If there is not enough in the bucket,
	 * wait until there is (if $callback is not set), or immediately return, and
	 * call $callback once there is enough.
	 *
	 * @param int          $amount   How much to take
	 * @param null|Closure $callback If set, a Closure to call back once there
	 *                               is enough in the bucket and it's our turn
	 *
	 * @phpstan-param positive-int $amount
	 * @phpstan-param null|Closure(): mixed $callback
	 *
	 * @throws Error
	 * @throws InvalidArgumentException
	 */
	public function take(int $amount=1, ?\Closure $callback=null): void {
		/** @phpstan-ignore-next-line */
		if ($amount < 1) {
			throw new InvalidArgumentException(
				__CLASS__ ."::take(\$amount) needs to be a positive integer."
			);
		}
		if ($amount > $this->size) {
			throw new InvalidArgumentException(
				__CLASS__ ."::take(\$amount) cannot be higher than the bucket's size"
			);
		}
		if ($this->fill < $amount) {
			$this->logger?->debug("Client wants {amount}, bucket has {fill}/{size}, waiting for refill", [
				"amount" => $amount,
				"fill" => $this->fill,
				"size" => $this->size,
			]);
			$suspension = EventLoop::getSuspension();
			$this->queue []= new QueueEntry(
				callback: $callback ?? $suspension,
				amount: $amount
			);
			if (!isset($callback)) {
				$suspension->suspend();
				$this->logger?->debug("Bucket got {fill}/{size} again, resuming", [
					"fill" => $this->fill,
					"size" => $this->size,
				]);
			}
			$this->start();
		} else {
			$this->logger?->debug("Client wants {amount}, bucket got {fill}/{size} in it, continuing", [
				"amount" => $amount,
				"fill" => $this->fill,
				"size" => $this->size,
			]);
			$this->fill -= $amount;
			$this->start();
			if (isset($callback)) {
				$callback();
			}
		}
	}

	protected function start(): void {
		if (isset($this->cancellationToken)) {
			return;
		}
		$this->logger?->debug("Starting refill-thread");
		$this->cancellationToken = EventLoop::repeat(
			$this->refillDelay,
			fn () => $this->put($this->refillAmount)
		);
	}

	protected function stop(): void {
		if (!isset($this->cancellationToken)) {
			return;
		}
		$this->logger?->debug("Stopping refill-thread");
		EventLoop::cancel($this->cancellationToken);
		$this->cancellationToken = null;
	}

	protected function put(int $refillAmount): void {
		$this->fill = min($this->size, $this->fill + $refillAmount);
		$this->logger?->debug("Refilling bucket with {refill_amount}, now at {fill}/{size}", [
				"refill_amount" => $refillAmount,
				"fill" => $this->fill,
				"size" => $this->size,
		]);
		if ($this->size <= $this->fill && !count($this->queue)) {
			$this->stop();
			return;
		}
		if (!count($this->queue)) {
			return;
		}
		$this->logger?->debug("{num_consumers} waiting, oldest one needs {needed}", [
			"num_consumers" => count($this->queue),
			"needed" => $this->queue[0]->amount,
		]);
		if ($this->queue[0]->amount > $this->fill) {
			return;
		}
		$nextItem = array_shift($this->queue);
		$this->fill -= $nextItem->amount;
		$callback = $nextItem->callback;
		if ($callback instanceof Suspension) {
			$callback->resume();
		} else {
			$callback();
		}
	}
}
