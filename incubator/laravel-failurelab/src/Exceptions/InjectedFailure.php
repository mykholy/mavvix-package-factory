<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Exceptions;

use RuntimeException;

/** A controlled exception that simulates interruption; it does NOT kill a worker. */
final class InjectedFailure extends RuntimeException
{
    public function __construct(
        public readonly string $checkpoint,
        public readonly int $attempt,
    ) {
        parent::__construct("FailureLab interrupted at [{$checkpoint}] on attempt {$attempt}.");
    }
}
