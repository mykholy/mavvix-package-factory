<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Internal;

/** Injectable failure schedule: matching checkpoint, exactly one selected attempt. */
final readonly class FaultRule
{
    public function __construct(public string $checkpoint, public int $onAttempt) {}
}
