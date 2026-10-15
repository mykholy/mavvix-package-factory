<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use InvalidArgumentException;
use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\Exceptions\InjectedFault;

final class Scenario
{
    /** @var array<string, int> 1-based occurrence at which a fault is injected */
    private array $faults = [];

    /** @var array<string, int> all checkpoint invocations within this scenario */
    private array $hits = [];

    /** @var list<AttemptOutcome> */
    private array $attempts = [];

    private int $startedAttempts = 0;

    public function __construct(
        private readonly FailureLab $lab,
        public readonly string $name,
    ) {
        if (trim($name) === '' || strlen($name) > 120) {
            throw new InvalidArgumentException('Scenario name must contain 1–120 characters.');
        }
    }

    /**
     * Throw a single simulated fault at the Nth hit of a named checkpoint.
     *
     * Calls before/after the selected hit pass through. This is not a real
     * process crash and does not prove cross-process durability.
     */
    public function failOnHit(string $checkpoint, int $onHit = 1): self
    {
        $this->validateCheckpoint($checkpoint);

        if ($onHit < 1 || $onHit > 1000) {
            throw new InvalidArgumentException('onHit must be between 1 and 1000.');
        }

        if ($this->startedAttempts !== 0) {
            throw new InvalidArgumentException('Cannot change a scenario plan after attempts begin.');
        }

        $this->faults[$checkpoint] = $onHit;

        return $this;
    }

    public function failOnceAt(string $checkpoint): self
    {
        return $this->failOnHit($checkpoint);
    }

    /**
     * Execute exactly one attempt. Only our own injected fault is captured.
     *
     * Real application exceptions propagate untouched. The caller is always
     * responsible for choosing whether/when to retry and inspecting the outcome.
     */
    public function attempt(callable $operation): AttemptOutcome
    {
        $this->startedAttempts++;

        try {
            $value = $this->lab->execute($this, $operation);
            $outcome = AttemptOutcome::succeeded($value);
        } catch (InjectedFault $fault) {
            $outcome = AttemptOutcome::injected($fault);
        }

        $this->attempts[] = $outcome;

        return $outcome;
    }

    public function hit(string $checkpoint): void
    {
        $this->validateCheckpoint($checkpoint);
        $count = ($this->hits[$checkpoint] ?? 0) + 1;
        $this->hits[$checkpoint] = $count;

        if (($this->faults[$checkpoint] ?? null) === $count) {
            throw new InjectedFault($checkpoint, $count);
        }
    }

    public function assertHits(string $checkpoint, int $expected): self
    {
        $actual = $this->hits[$checkpoint] ?? 0;

        if ($expected < 0 || $actual !== $expected) {
            throw new ExpectationFailed(
                sprintf('Checkpoint "%s" was hit %d time(s); expected %d.', $checkpoint, $actual, $expected)
            );
        }

        return $this;
    }

    public function assertInjectedCount(int $expected): self
    {
        $actual = count(array_filter(
            $this->attempts,
            static fn (AttemptOutcome $result): bool => $result->wasInjected(),
        ));

        if ($expected < 0 || $actual !== $expected) {
            throw new ExpectationFailed(
                sprintf('Scenario "%s" injected %d fault(s); expected %d.', $this->name, $actual, $expected)
            );
        }

        return $this;
    }

    /**
     * Evaluate an explicit recovery invariant without printing underlying data.
     * The callback is application-defined; exceptions from it are not masked.
     */
    public function assertInvariant(string $name, callable $predicate): self
    {
        if (trim($name) === '' || strlen($name) > 120 || str_contains($name, "\n")) {
            throw new InvalidArgumentException('Invariant name must be a single line of 1–120 characters.');
        }

        if ($predicate() !== true) {
            throw new ExpectationFailed(sprintf('Recovery invariant failed: %s.', $name));
        }

        return $this;
    }

    /** Safe, deterministic metadata only; no provider response values. */
    public function summary(): array
    {
        $hits = $this->hits;
        ksort($hits);

        return [
            'scenario' => $this->name,
            'attempts' => count($this->attempts),
            'simulated_faults' => count(array_filter(
                $this->attempts,
                static fn (AttemptOutcome $outcome): bool => $outcome->wasInjected(),
            )),
            'checkpoint_hits' => $hits,
        ];
    }

    /** @return list<AttemptOutcome> */
    public function outcomes(): array
    {
        return $this->attempts;
    }

    private function validateCheckpoint(string $checkpoint): void
    {
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9._:-]{0,119}$/D', $checkpoint)) {
            throw new InvalidArgumentException(
                'Checkpoint must start with a letter and contain up to 120 safe ASCII characters.'
            );
        }
    }
}
