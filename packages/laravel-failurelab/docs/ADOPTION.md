# Adoption guide

## 15-minute trial

1. Checkout the factory repository and point Composer at its `packages/laravel-failurelab` directory.
2. Install in a disposable Laravel 12/13 test app. Ensure `APP_ENV=testing` when executing PHPUnit.
3. Add one explicit checkpoint after a **fake** external provider confirms success, before your application updates local state.
4. Create one Feature Test using `scenario()->failOnceAt()` and two explicit `attempt()` calls.
5. Assert both the fake-provider operation count and the persisted local result.
6. Compare code/time against native PHPUnit and Laravel Resilience. If FailureLab adds no value, don't adopt it.

## Required user research

Record at least three independent engineers' attempts on actual codebases (with private data removed). Track implementation time, failures found, readability, safety concerns and alternative-tool effectiveness.

## Upgrade expectations

No SemVer stability promise until a reviewed stable release. Pre-alpha APIs may change. Avoid copying the prototype into high-risk systems or CI jobs connected to live services.
