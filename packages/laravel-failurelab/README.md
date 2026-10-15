# Laravel FailureLab

> **Know what happens after the failure — before your customers do.**

A deterministic, test-only **failure-boundary and replay harness** for Laravel. Simulate a failure at an explicit application checkpoint, replay the operation in the same test, and assert whether recovery preserved your invariants.

**Status: pre-alpha prototype. Not on Packagist. Not a stable release.**

## Why another testing tool?

A payment gateway accepts a charge, but your process loses the acknowledgement. A webhook commits your database changes, then the response fails. If the operation runs again, will it charge twice or apply an effect twice?

Laravel's built-in fakes are excellent for unit behavior. **FailureLab tests your recovery contract** after one precisely selected fault. It **does not** replace Race Lab's cross-process concurrency scheduler or Laravel Resilience's dependency fault injection.

| FailureLab does | FailureLab does not |
| --- | --- |
| Reproducibly throw at the Nth named checkpoint | Kill a process or simulate a real SIGKILL |
| Run attempt A and explicit replay B within a test | Promise automatic recovery, retries or delivery |
| Assert checkpoint count and simulated-failure boundaries | Guarantee exactly-once external effects |
| Work with real disposable SQLite models or safe fakes | Run destructive chaos experiments |
| Refuse scenario activation outside CLI `APP_ENV=testing` | Inject faults into production HTTP requests |

## Quick start

Use this pre-alpha as a **local Composer path repository** until it has a dedicated repository, public validation and release:

```bash
# From your Laravel application's directory, point to this checked-out folder:
composer config repositories.failurelab '{"type":"path","url":"../mavvix-package-factory/packages/laravel-failurelab"}'
composer require mykholy/laravel-failurelab:@dev
```

It is registered through Laravel package discovery. The package can be a normal `require` dependency because instrumentation in your application source calls its `checkpoint()` method. The checkpoint is a no-op unless an explicit test scenario is currently active.

**Never enable scenarios via an HTTP route.** Only CLI tests with `APP_ENV=testing` are permitted. No override switch.

## Your first failure/replay test

A service contains an explicit boundary:

```php
use Mykholy\FailureLab\FailureLab;

final class CheckoutService
{
    public function __construct(
        private FailureLab $failureLab,
        private PaymentGateway $gateway,
    ) {}

    public function capture(string $orderId): string
    {
        $reference = $this->gateway->capture(
            $orderId,
            idempotencyKey: 'capture:'.$orderId,
        );

        // Provider may have succeeded, while local persistence has not.
        $this->failureLab->checkpoint('checkout.after_provider_acceptance');

        Order::findOrFail($orderId)->update(['reference' => $reference]);

        return $reference;
    }
}
```

Then write a real Feature Test:

```php
use Mykholy\FailureLab\FailureLab;

// $service: application service using an explicitly idempotent fake gateway.
// $order: fresh order on a disposable test database.
$lab = app(FailureLab::class);
$scenario = $lab->scenario('provider accepted but local update lost')
    ->failOnceAt('checkout.after_provider_acceptance');

$scenario->attempt(fn () => $service->capture($order->id))
    ->assertInjectedAt('checkout.after_provider_acceptance');

// First attempt intentionally stopped *after* the fake provider call.
$this->assertNull($order->fresh()->reference);

$scenario->attempt(fn () => $service->capture($order->id))
    ->assertSucceeded();

$scenario
    ->assertHits('checkout.after_provider_acceptance', 2)
    ->assertInjectedCount(1)
    ->assertInvariant(
        'the provider was captured only once',
        fn (): bool => $gateway->captures === 1,
    );

// Machine-readable metadata, with no provider response values:
$this->assertSame(2, $scenario->summary()['attempts']);

// Also assert provider fake capture count and persisted order state.
// FailureLab does not implement provider idempotency for you.
```

Use `->failOnHit('name', 2)` to target the second occurrence. Normal application exceptions propagate: the harness never hides them as injected faults.

`assertInvariant(name, predicate)` executes an application-specific assertion after replay. It reports the invariant's **name only** when false, not the sensitive values checked. The `summary()` array reports scenario name, number of attempts, simulated-fault count and per-checkpoint hit counts, so it can be included in test diagnostics without logging external responses.

## Why the boundary matters

A retry is **not automatically safe**. FailureLab exposes the point where control is lost, but your application must use appropriate provider idempotency keys, durable receipts, transactions, outbox events and reconciliation policies. For workflows that need durable operation ownership, see [FlowGuard](https://github.com/mykholy/laravel-flowguard).

## Verification

```bash
composer install
composer test
composer format:check
```

The included integration tests exercise:
- fake payment provider acceptance followed by a simulated local acknowledgement failure
- committed webhook effects followed by a simulated lost response
- repeated invocation without duplicate *local* effects
- Nth-hit selection, validation, exception propagation, scope cleanup and production-environment refusal

These tests do not prove crash safety, true parallel concurrency or real-provider financial correctness. CI results must pass before further publication.

A GitHub Actions artifact containing a clean pre-alpha source archive is generated after the compatibility matrix succeeds. Extract it and use a Composer `path` repository for experiments (not production deployments).

## Supported baseline (pre-alpha target)

- PHP 8.3+
- Laravel 12 / 13
- SQLite for the current disposable integration fixture
- No database migration, production HTTP middleware, global exception handler or custom daemon

See [design and threat model](docs/DESIGN.md), [adoption guide](docs/ADOPTION.md) and [market comparison](docs/COMPETITORS.md).

## Development and release policy

We prioritize verified behavior over features or release numbers. The pre-alpha lives under `packages/laravel-failurelab` in [Mavvix Package Factory](https://github.com/mykholy/mavvix-package-factory). A dedicated top-level repository and Packagist submission are **not completed**. Do not copy this README into a standalone repo and claim a stable release without running the full PHP/Laravel matrix, completing reviewer signoff and validating external adoption.

Maintainer: [@mykholy](https://github.com/mykholy) · MIT License.
