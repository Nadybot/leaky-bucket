<?php declare(strict_types=1);

namespace Nadylib\LeakyBucket;

use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

class LeakyBucket {
	protected int $fill = 0;

	/** @var QueueEntry[] */
	protected array $queue = [];

	protected ?string $cancellationToken = null;

	final public function __construct(
		public int $size,
		public float $refillDelay,
		public int $refillAmount=1,
		?int $startAmount=null,
		protected ?LoggerInterface $logger=null,
	) {
		$this->fill = $startAmount ?? $this->size;
	}

	public function take(int $amount=1, ?\Closure $callback=null): void {
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
			function (string $token): void {
				$this->put($this->refillAmount);
			}
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
		if ($this->size === $this->fill && !count($this->queue)) {
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
