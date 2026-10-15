<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use Illuminate\Support\ServiceProvider;

final class FailureLabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FailureLab::class, fn ($app) => new FailureLab($app));
    }
}
