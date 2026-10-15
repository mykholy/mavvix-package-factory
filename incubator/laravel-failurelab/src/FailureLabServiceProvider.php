<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use Illuminate\Support\ServiceProvider;
use Mykholy\FailureLab\Internal\EnvironmentGuard;

final class FailureLabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnvironmentGuard::class);
        $this->app->singleton(FailureLabManager::class);
    }
}
