<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use InvalidArgumentException;
use Mykholy\FailureLab\Internal\EnvironmentGuard;
use Mykholy\FailureLab\Internal\Names;
use Throwable;

/** Sequential redelivery, not proof of parallel execution or transport exactly-once. */
final class Replay
{
    private int $deliveries = 2;

    public function __construct(public readonly string $name, private readonly EnvironmentGuard $guard)
    {
        Names::scenario($name);
    }

    public function times(int $deliveries): self
    {
        if ($deliveries < 1 || $deliveries > 20) {
            throw new InvalidArgumentException('Replay deliveries must be between 1 and 20.');
        }

        $next = clone $this;
        $next->deliveries = $deliveries;

        return $next;
    }

    /** @param callable(int): mixed $handler */
    public function run(callable $handler): ReplayReport
    {
        $this->guard->assertTesting();
        $attempts = [];

        for ($delivery = 1; $delivery <= $this->deliveries; $delivery++) {
            try {
                $attempts[] = new Attempt($delivery, 'completed', $handler($delivery));
            } catch (Throwable $error) {
                $attempts[] = new Attempt($delivery, 'failed', error: $error);
                break; // Fail closed: do not automatically retry unknown side effects.
            }
        }

        return new ReplayReport($this->name, $attempts);
    }
}
