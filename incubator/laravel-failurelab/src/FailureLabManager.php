<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use Mykholy\FailureLab\Internal\EnvironmentGuard;

/** Injectable alternative to the static entry point. */
final readonly class FailureLabManager
{
    public function __construct(private EnvironmentGuard $guard) {}

    public function scenario(string $name): Scenario
    {
        return new Scenario($name, $this->guard);
    }

    public function replay(string $name): Replay
    {
        return new Replay($name, $this->guard);
    }
}
