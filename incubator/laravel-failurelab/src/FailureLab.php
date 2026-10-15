<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use Mykholy\FailureLab\Internal\EnvironmentGuard;

/** Human-friendly entry point; there is no global mutable test state. */
final class FailureLab
{
    public static function scenario(string $name): Scenario
    {
        return new Scenario($name, new EnvironmentGuard);
    }

    public static function replay(string $name): Replay
    {
        return new Replay($name, new EnvironmentGuard);
    }
}
