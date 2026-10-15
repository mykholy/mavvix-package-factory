<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Internal;

use Mykholy\FailureLab\Contracts\Checkpoint;
use Mykholy\FailureLab\Exceptions\InjectedFailure;

final class ActiveCheckpoint implements Checkpoint
{
    /** @param list<FaultRule> $rules */
    public function __construct(
        private readonly int $attempt,
        private readonly array $rules,
        private array &$hits,
        private array &$interruptions,
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

            if (isset($this->interruptions[$key])) {
                continue;
            }

            $this->interruptions[$key] = true;

            throw new InjectedFailure($name, $this->attempt);
        }
    }
}
