<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\Reports\AssertsRecoveryInvariants;

final readonly class ReplayReport
{
    use AssertsRecoveryInvariants;

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

    /** Assert the number of handler exceptions recorded during sequential replay. */
    public function assertFailedDeliveries(int $expected): self
    {
        if ($expected < 0) {
            throw new \InvalidArgumentException('Expected failed deliveries must not be negative.');
        }

        $actual = count(array_filter(
            $this->deliveries,
            static fn (Attempt $delivery): bool => $delivery->status === 'failed',
        ));

        if ($actual !== $expected) {
            throw new ExpectationFailed(
                "Replay [{$this->name}] expected {$expected} failed deliveries; got {$actual}."
            );
        }

        return $this;
    }

    /**
     * A later handler invocation completed after an earlier failed invocation.
     *
     * This asserts handler completion ONLY, not correctness or external idempotency.
     * Pair with assertInvariant() for the business effect and provider-call checks.
     */
    public function assertRecoveredAfterFailure(): self
    {
        $sawFailure = false;

        foreach ($this->deliveries as $delivery) {
            if ($delivery->status === 'failed') {
                $sawFailure = true;

                continue;
            }

            if ($sawFailure && $delivery->status === 'completed') {
                return $this;
            }
        }

        throw new ExpectationFailed(
            "Replay [{$this->name}] has no completed handler delivery following a failed delivery."
        );
    }

    /** Bounded counters only. This does not include payloads or provider output. */
    public function summary(): array
    {
        return [
            'scenario' => $this->name,
            'deliveries' => count($this->deliveries),
            'completed' => count(array_filter($this->deliveries, static fn (Attempt $attempt): bool => $attempt->status === 'completed')),
            'failed' => count(array_filter($this->deliveries, static fn (Attempt $attempt): bool => $attempt->status === 'failed')),
        ];
    }

    public function assertAllCompleted(): self
    {
        foreach ($this->deliveries as $delivery) {
            if ($delivery->status !== 'completed') {
                $exception = $delivery->error === null ? 'none' : get_debug_type($delivery->error);
                throw new ExpectationFailed(
                    "Replay [{$this->name}] delivery {$delivery->number} failed: exception={$exception}. "
                    .'Exception messages are intentionally redacted.'
                );
            }
        }

        if ($this->deliveries === []) {
            throw new ExpectationFailed("Replay [{$this->name}] executed no deliveries.");
        }

        return $this;
    }
}
