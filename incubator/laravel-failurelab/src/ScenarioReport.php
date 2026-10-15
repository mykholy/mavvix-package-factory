<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\Internal\Names;
use Mykholy\FailureLab\Reports\AssertsRecoveryInvariants;

/** Assertions throw on failure and integrate naturally into PHPUnit/Pest tests. */
final readonly class ScenarioReport
{
    use AssertsRecoveryInvariants;
    /**
     * @param list<Attempt> $attempts
     * @param array<string, int> $hits
     * @param list<string> $interruptions
     */
    public function __construct(
        public string $name,
        public array $attempts,
        public array $hits,
        public array $interruptions,
    ) {}

    public function assertAttempts(int $expected): self
    {
        $actual = count($this->attempts);
        if ($actual !== $expected) {
            throw new ExpectationFailed("Scenario [{$this->name}] expected {$expected} attempts; got {$actual}.");
        }

        return $this;
    }

    public function assertInterruptedAt(string $checkpoint, int $times = 1): self
    {
        Names::checkpoint($checkpoint);
        $actual = 0;
        foreach ($this->interruptions as $event) {
            if (str_starts_with($event, $checkpoint.'@')) {
                $actual++;
            }
        }

        if ($actual !== $times) {
            throw new ExpectationFailed("Expected interruption [{$checkpoint}] {$times} time(s); got {$actual}.");
        }

        return $this;
    }

    public function assertCheckpointHit(string $checkpoint, int $times): self
    {
        Names::checkpoint($checkpoint);
        $actual = $this->hits[$checkpoint] ?? 0;
        if ($actual !== $times) {
            throw new ExpectationFailed("Expected checkpoint [{$checkpoint}] hit {$times} time(s); got {$actual}.");
        }

        return $this;
    }

    public function assertCompleted(): self
    {
        if ($this->attempts === [] || $this->attempts[array_key_last($this->attempts)]->status !== 'completed') {
            $last = $this->attempts[array_key_last($this->attempts)] ?? null;
            $status = $last?->status ?? 'not-started';
            $exception = $last?->error === null ? 'none' : get_debug_type($last->error);
            throw new ExpectationFailed(
                "Scenario [{$this->name}] did not complete: status={$status}; exception={$exception}. "
                .'Exception messages are intentionally redacted.'
            );
        }

        return $this;
    }

    /** Summary contains only execution metadata, never callback values or exception messages. */
    public function summary(): array
    {
        $hits = $this->hits;
        ksort($hits);

        return [
            'scenario' => $this->name,
            'attempts' => count($this->attempts),
            'completed' => count(array_filter($this->attempts, static fn (Attempt $attempt): bool => $attempt->status === 'completed')),
            'interrupted' => count($this->interruptions),
            'checkpoint_hits' => $hits,
        ];
    }

    public function value(): mixed
    {
        $this->assertCompleted();

        return $this->attempts[array_key_last($this->attempts)]->value;
    }
}
