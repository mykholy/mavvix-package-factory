<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use Mykholy\FailureLab\Exceptions\ExpectationFailed;

final readonly class ReplayReport
{
    /** @param list<Attempt> $deliveries */
    public function __construct(public string $name, public array $deliveries) {}

    public function assertDeliveries(int $expected): self
    {
        $actual = count($this->deliveries);
        if ($actual !== $expected) {
            throw new ExpectationFailed("Replay [{$this->name}] expected {$expected} deliveries; got {$actual}.");
        }

        return $this;
    }

    public function assertAllCompleted(): self
    {
        foreach ($this->deliveries as $delivery) {
            if ($delivery->status !== 'completed') {
                $reason = $delivery->error?->getMessage() ?? 'Unknown failure';
                throw new ExpectationFailed("Replay [{$this->name}] delivery {$delivery->number} failed: {$reason}");
            }
        }

        if ($this->deliveries === []) {
            throw new ExpectationFailed("Replay [{$this->name}] executed no deliveries.");
        }

        return $this;
    }
}
