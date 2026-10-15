<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use Throwable;

/** Immutable evidence about one in-process invocation of the user's operation. */
final readonly class Attempt
{
    public function __construct(
        public int $number,
        public string $status,
        public mixed $value = null,
        public ?Throwable $error = null,
    ) {}
}
