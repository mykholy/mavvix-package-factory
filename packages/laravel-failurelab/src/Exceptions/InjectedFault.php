<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Exceptions;

use RuntimeException;

final class InjectedFault extends RuntimeException
{
    public function __construct(
        public readonly string $checkpoint,
        public readonly int $occurrence,
    ) {
        parent::__construct(sprintf(
            'FailureLab simulated fault at "%s" (hit #%d). This is not a process crash.',
            $checkpoint,
            $occurrence,
        ));
    }
}
