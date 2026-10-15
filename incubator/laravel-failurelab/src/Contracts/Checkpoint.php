<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Contracts;

interface Checkpoint
{
    /** An explicit, opt-in test seam. Never a real process termination. */
    public function hit(string $name): void;
}
