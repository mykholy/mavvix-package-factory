<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use Illuminate\Contracts\Foundation\Application;
use Mykholy\FailureLab\Exceptions\NestedScenario;
use Mykholy\FailureLab\Exceptions\UnsafeEnvironment;

final class FailureLab
{
    private ?Scenario $active = null;

    public function __construct(private readonly Application $application)
    {
    }

    /**
     * Explicitly opt into a testing-only failure scenario.
     */
    public function scenario(string $name): Scenario
    {
        $this->assertTestingEnvironment();

        return new Scenario($this, $name);
    }

    /**
     * Safe no-op outside an active test scenario, including production.
     *
     * Place this at a *well-defined* boundary in application code. It does
     * not kill a worker, roll back external effects, or alter a transaction.
     */
    public function checkpoint(string $name): void
    {
        $this->active?->hit($name);
    }

    /** @internal Used by Scenario; never schedule or invoke from HTTP routes. */
    public function execute(Scenario $scenario, callable $operation): mixed
    {
        $this->assertTestingEnvironment();

        if ($this->active !== null) {
            throw new NestedScenario('Nested or overlapping scenarios are not supported.');
        }

        $this->active = $scenario;

        try {
            return $operation();
        } finally {
            $this->active = null;
        }
    }

    private function assertTestingEnvironment(): void
    {
        if (PHP_SAPI !== 'cli' || ! $this->application->environment('testing')) {
            throw new UnsafeEnvironment(
                'FailureLab scenarios require the CLI and APP_ENV=testing. No production override exists.'
            );
        }
    }
}
