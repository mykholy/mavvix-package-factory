<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Internal;

use Mykholy\FailureLab\Contracts\Checkpoint;
use Mykholy\FailureLab\Exceptions\InjectedFailure;

final class ActiveCheckpoint implements Checkpoint
{
    /** @var array<string, InjectedFailure> */
    private array $issuedFailures = [];

    /** @param list<FaultRule> $rules */
    public function __construct(
        private readonly int $attempt,
        private readonly array $rules,
        private array &$hits,
    ) {}

    public function hit(string $name): void
    {
        Names::checkpoint($name);
        $this->hits[$name] = ($this->hits[$name] ?? 0) + 1;

        foreach ($this->rules as $rule) {
            if ($rule->checkpoint !== $name || $rule->onAttempt !== $this->attempt) {
                continue;
            }

            $key = $rule->checkpoint.'@'.$rule->onAttempt;

            if (isset($this->issuedFailures[$key])) {
                continue;
            }

            $failure = new InjectedFailure($name, $this->attempt);
            $this->issuedFailures[$key] = $failure;

            throw $failure;
        }
    }

    /** Recognize only the exact exception instance emitted by this attempt's checkpoint. */
    public function issuedRuleFor(InjectedFailure $error): ?string
    {
        foreach ($this->issuedFailures as $rule => $issued) {
            if ($issued === $error) {
                return $rule;
            }
        }

        return null;
    }
}
