<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests;

use Mykholy\FailureLab\FailureLabServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [FailureLabServiceProvider::class];
    }
}
