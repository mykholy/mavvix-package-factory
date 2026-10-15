<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Internal;

use Mykholy\FailureLab\Exceptions\UnsafeEnvironment;

final class EnvironmentGuard
{
    public function assertTesting(): void
    {
        // Fail closed. This protects accidental use from commands and production workers.
        // PHPUnit can set APP_ENV=testing in phpunit.xml.
        $environment = getenv('APP_ENV');

        if ($environment !== 'testing' || PHP_SAPI !== 'cli') {
            throw new UnsafeEnvironment(
                'FailureLab is test-only: CLI and APP_ENV=testing are required. '.
                'Never inject faults into live endpoints, real providers, or production data.'
            );
        }
    }
}
