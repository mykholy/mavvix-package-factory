<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use InvalidArgumentException;
use Mykholy\FailureLab\Contracts\Checkpoint;
use Mykholy\FailureLab\Exceptions\InjectedFailure;
use Mykholy\FailureLab\Internal\ActiveCheckpoint;
use Mykholy\FailureLab\Internal\EnvironmentGuard;
use Mykholy\FailureLab\Internal\FaultRule;
use Mykholy\FailureLab\Internal\Names;
use Throwable;

/** In-process, test-only controlled interruption; never claims SIGKILL semantics. */
final class Scenario
{
    /** @var list<FaultRule> */
    private array $rules = [];

    private int $maxAttempts = 1;

    public function __construct(public readonly string $name, private readonly EnvironmentGuard $guard)
    {
        Names::scenario($name);
    }

    public function interruptOnceAt(string $checkpoint, int $onAttempt = 1): self
    {
        Names::checkpoint($checkpoint);

        if ($onAttempt < 1 || $onAttempt > 20) {
            throw new InvalidArgumentException('Attempt index must be between 1 and 20.');
        }

        foreach ($this->rules as $rule) {
            if ($rule->checkpoint === $checkpoint && $rule->onAttempt === $onAttempt) {
                throw new InvalidArgumentException('Duplicate fault rule for checkpoint and attempt.');
            }
        }

        $next = clone $this;
        $next->rules[] = new FaultRule($checkpoint, $onAttempt);

        return $next;
    }

    /** Total attempts, including the initial invocation. */
    public function attempts(int $count): self
    {
        if ($count < 1 || $count > 20) {
            throw new InvalidArgumentException('Scenario attempts must be between 1 and 20.');
        }

        $next = clone $this;
        $next->maxAttempts = $count;

        return $next;
    }

    /**
     * @param  callable(Checkpoint, int): mixed  $operation
     *                                                       Each invocation is explicitly chosen by the caller; persistent state belongs to the host app.
     */
    public function run(callable $operation): ScenarioReport
    {
        $this->guard->assertTesting();

        $attempts = [];
        $hits = [];
        $interruptions = [];

        for ($index = 1; $index <= $this->maxAttempts; $index++) {
            $points = new ActiveCheckpoint($index, $this->rules, $hits);

            try {
                $value = $operation($points, $index);
                $attempts[] = new Attempt($index, 'completed', $value);
                break;
            } catch (InjectedFailure $failure) {
                $rule = $points->issuedRuleFor($failure);
                if ($rule === null) {
                    // A callback may throw the public exception type itself. Never trust its class alone.
                    $attempts[] = new Attempt($index, 'failed', error: $failure);
                    break;
                }

                // An interruption counts only if it escaped the handler back to the runner.
                $interruptions[$rule] = true;
                $attempts[] = new Attempt($index, 'interrupted', error: $failure);
            } catch (Throwable $error) {
                $attempts[] = new Attempt($index, 'failed', error: $error);
                break;
            }
        }

        return new ScenarioReport(
            $this->name,
            $attempts,
            $hits,
            array_keys($interruptions),
            array_map(
                static fn (FaultRule $rule): string => $rule->checkpoint.'@'.$rule->onAttempt,
                $this->rules,
            ),
        );
    }
}
