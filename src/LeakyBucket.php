<?php declare(strict_types=1);

namespace Nadylib\LeakyBucket;

use Psr\Log\LoggerInterface;
use Revolt\EventLoop;

class LeakyBucket {
	private int $fill = 0;

	/** @var QueueEntry[] */
	private array $queue = [];

	private ?string $cancellationToken = null;

	public function __construct(
		public int $size,
		public float $refillDelay,
		public int $refillAmount=1,
		?int $startAmount=null,
		private ?LoggerInterface $logger=null,
	) {
		$this->fill = $startAmount ?? $this->size;
	}

	public function take(int $amount=1): void {
		if ($this->fill < $amount) {
			$this->logger?->debug("Client wants {amount}, bucket has {fill}/{size}, waiting for refill", [
				"amount" => $amount,
				"fill" => $this->fill,
				"size" => $this->size,
			]);
			$suspension = EventLoop::getSuspension();
			$this->queue []= new QueueEntry(
				suspension: $suspension,
				amount: $amount
			);
			$suspension->suspend();
			$this->logger?->debug("Bucket got {fill}/{size} again, resuming", [
				"fill" => $this->fill,
				"size" => $this->size,
			]);
		} else {
			$this->logger?->debug("Client wants {amount}, bucket got {fill}/{size} in it, continuing", [
				"amount" => $amount,
				"fill" => $this->fill,
				"size" => $this->size,
			]);
		}
		$this->fill -= $amount;
		$this->start();
	}

	private function start(): void {
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

	private function stop(): void {
		if (!isset($this->cancellationToken)) {
			return;
		}
		$this->logger?->debug("Stopping refill-thread");
		EventLoop::cancel($this->cancellationToken);
		$this->cancellationToken = null;
	}

	private function put(int $refillAmount): void {
		$this->fill = min($this->size, $this->fill + $refillAmount);
		$this->logger?->debug("Refilling bucket with {refill_amount}, now at {fill}/{size}", [
				"refill_amount" => $refillAmount,
				"fill" => $this->fill,
				"size" => $this->size,
		]);
		if (count($this->queue)) {
			if ($this->queue[0]->amount <= $this->fill) {
				$this->logger?->debug("{num_consumers} waiting, calling oldest one", [
					"num_consumers" => count($this->queue),
				]);
				array_shift($this->queue)->suspension->resume();
			} else {
				$this->logger?->debug("{num_consumers} waiting, oldest one needs {needed}", [
					"num_consumers" => count($this->queue),
					"needed" => $this->queue[0]->amount,
				]);
			}
		} elseif ($this->size === $this->fill) {
			$this->stop();
		}
	}
}
