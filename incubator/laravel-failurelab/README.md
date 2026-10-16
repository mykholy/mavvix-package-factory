<p align="center">
  <strong>FAILURELAB</strong><br>
  <em>Break the test. Prove the recovery.</em>
</p>

<h1 align="center">Laravel FailureLab</h1>
<p align="center">Deterministic fault injection and replay assertions for Laravel integration tests.</p>
<p align="center">
  <a href="https://github.com/mykholy/mavvix-package-factory/actions/workflows/failurelab-incubator.yml"><img alt="Incubator CI" src="https://github.com/mykholy/mavvix-package-factory/actions/workflows/failurelab-incubator.yml/badge.svg"></a>
  <img alt="PHP 8.3+" src="https://img.shields.io/badge/PHP-8.3%2B-777BB4">
  <img alt="Laravel 12 or 13" src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20">
  <a href="LICENSE"><img alt="MIT" src="https://img.shields.io/badge/license-MIT-green"></a>
</p>

> **Pre-alpha / incubator source.** This repository content has not yet been released as a stable Composer package. Do not assume the Packagist link resolves until a real version is published and verified.

A payment provider accepts a charge, but your application throws before saving the result. A webhook arrives three times. How do you prove your code won't create the local effect twice?

FailureLab runs the **same operation under controlled, named failure points**, records which attempts actually ran, and makes the assertions readable. It never intercepts HTTP or background workers by magic; you explicitly opt into each test seam.

```text
     your test                  app behavior                 the evidence
 ┌─────────────────┐        ┌───────────────────┐      ┌────────────────┐
 │ Scenario        │ -----> │ fake provider      │      │ Attempt #1     │
 │ fail once at    │        │ accepts capture    │ ---> │ interrupted    │
 │ provider.accepted│       │ checkpoint.hit()  │      ├────────────────┤
 │ retry once      │ -----> │ retry operation    │ ---> │ Attempt #2     │
 └─────────────────┘        │ persist locally   │      │ completed      │
                            └───────────────────┘      └────────────────┘
```

## Why another testing package?

Laravel's Queue/Bus fakes verify dispatch behavior. [Race Lab](https://packagist.org/packages/race-lab/laravel-race-lab) tests **concurrent workers at checkpoints**. FailureLab focuses on **bounded sequential replay of one business operation** after a controlled in-process interruption: a reproducible recovery assertion suitable for an isolated PHPUnit test.

It does **not** replace Race Lab, Horizon, Pulse, FlowGuard, Testbench, real subprocess crash tests or real distributed-systems integration tests. It makes no exactly-once delivery guarantee.

## Installation

**Not published yet.** Clone/extract the package source into `../laravel-failurelab`, or use the incubator directory from the factory repository. Then configure a local path repository in a Laravel 12/13 test application:

```bash
composer config repositories.failurelab '{"type":"path","url":"../laravel-failurelab","options":{"symlink":true}}'
composer require --dev mykholy/laravel-failurelab:@dev
```

When a tagged, verified release is published to Packagist, the intended installation will be:

```bash
composer require --dev mykholy/laravel-failurelab
```

**Requirements:** PHP 8.3+, Laravel 12 or 13, PHPUnit/Pest in the host app. Keep this in `require-dev`. Set `APP_ENV=testing` in `phpunit.xml` and use a disposable test database. No migrations, middleware, queues, services, or network access are installed by FailureLab.

## Example: a provider succeeded, but local state was interrupted

```php
use Mykholy\FailureLab\FailureLab;

$report = FailureLab::scenario('capture accepted, local commit interrupted')
    ->interruptOnceAt('provider.accepted')
    ->attempts(2) // total executions, including the first
    ->run(function ($checkpoint) use ($checkout): string {
        $reference = $checkout->captureUsingFakeGateway('order-42');
        $checkpoint->hit('provider.accepted'); // controlled test seam
        $checkout->markPaid('order-42', $reference);

        return $reference;
    });

$report
    ->assertAttempts(2)
    ->assertInterruptedAt('provider.accepted')
    ->assertFaultPlanSatisfied() // confirm every configured fault actually fired
    ->assertCheckpointHit('provider.accepted', 2)
    ->assertCompleted()
    ->assertInvariant('provider accepted once', fn (): bool => $fakeGateway->acceptedCaptures('order-42') === 1);

// Also assert the BUSINESS INVARIANT using the app's DB and fake provider:
$this->assertSame(1, $fakeGateway->acceptedCaptures('order-42'));
$this->assertSame('paid', Order::findOrFail(42)->status);
```

**Avoid false-green tests:** a scenario can complete successfully without ever
reaching a configured fault. Always pair `assertFaultPlanSatisfied()` with
`assertCompleted()` when your test is intended to prove recovery. The first
assertion verifies every configured checkpoint/attempt was actually interrupted;
the second verifies the operation eventually completed. Neither proves your
payment or database invariant by itself.

The fake gateway **must implement provider-side idempotency** for this assertion to be meaningful. FailureLab does not make an unsafe checkout safe; it exposes whether your integration handles the replay. Real external acceptance and unknown outcomes require reconciliation logic in your app.

## Example: the same webhook delivered three times

```php
FailureLab::replay('verified event evt-123')
    ->times(3)
    ->run(fn () => $webhookHandler->handle($sameVerifiedEvent))
    ->assertDeliveries(3)
    ->assertAllCompleted();

$this->assertDatabaseCount('ledger_entries', 1);
```

This is **sequential** replay, not a concurrent race test. Use your normal HTTP tests to check signature verification; use Race Lab or a process-isolated integration test to prove concurrency behavior.

### Example: failed delivery, then a successful retry

By default, the replay runner **stops after an unexpected exception**. To model a
transport sending the same message again *after a failed handler*, opt in:

```php
$report = FailureLab::replay('webhook failed then recovered')
    ->times(3)
    ->continueAfterFailure()
    ->run(function (int $delivery) use ($handler, $verifiedEvent): void {
        $handler->handle($verifiedEvent); // your application, using a disposable test DB
    });

$report->assertDeliveries(3)
    ->assertFailedDeliveries(1)
    ->assertRecoveredAfterFailure() // a later handler invocation succeeded
    ->assertInvariant('one committed ledger entry', fn (): bool => DB::table('ledger_entries')->count() === 1);
```

Configure your **test handler** to fail on a chosen delivery, e.g. throw inside a
database transaction the first time. FailureLab never injects an exception
into the replay callback automatically. A recovered handler call is **not**
proof of exactly-once delivery or correct financial state: use your own
`assertInvariant()` plus provider-fake assertions. Use the default stop-on-error
behavior when replaying potentially unsafe side effects.



After `run()`, `assertInvariant(name, predicate)` evaluates a project-specific recovery rule; failure messages include the rule's name only, not the values tested. `summary()` exposes attempt/checkpoint counters without leaking provider responses or arbitrary exception messages.

The current **canonical** incubator source is [here](https://github.com/mykholy/mavvix-package-factory/tree/main/incubator/laravel-failurelab). An earlier experimental implementation under `packages/` was retired to avoid two incompatible packages with the same Composer name.

## Guarantees and limits

| It does | It does **not** |
| --- | --- |
| Simulate a specific exception on a named checkpoint/attempt | Kill a worker, process, container or network connection |
| Bound re-execution to at most 20 attempts | Retry arbitrary application exceptions |
| Record attempts, exact interrupted points and checked checkpoints | Record every side effect or guarantee idempotency |
| Sequentially replay a callback up to 20 times | Simulate true parallel transport or race conditions |
| Fail closed when `APP_ENV` is not `testing` | Make calls to a real external payment provider safe |
| Work with regular PHPUnit/Pest assertions | Depend on a daemon, production middleware or migration |

For edge cases, see [Limitations & threat model](docs/SAFETY.md), [Design decisions](docs/DESIGN.md), and [Runnable examples](examples/README.md).

## GitHub incubation

The auditable source and automated test matrix currently live at [Mavvix Package Factory / FailureLab incubator](https://github.com/mykholy/mavvix-package-factory/tree/main/incubator/laravel-failurelab). The standalone `mykholy/laravel-failurelab` repository and its Packagist package have not been created or verified. Do not install from an unverified package of that name.

## Developer workflow

```bash
composer install
composer test
composer analyse
vendor/bin/pint --test
```

The CI matrix is intended to verify Laravel 12/13 on PHP 8.3/8.4/8.5, subject to upstream PHP and dependency support. For the first public release, run every matrix combination and a host-app integration demo; a badge or a version number is not evidence by itself.

## Public API at a glance

| Method | Purpose |
| --- | --- |
| `FailureLab::scenario(string $name)` | Start an isolated failure scenario |
| `->interruptOnceAt(string $point, int $onAttempt = 1)` | Inject one `InjectedFailure` exception at one precise attempt |
| `->attempts(int $count)` | Set maximum total attempts (1–20) |
| `->run(callable $operation)` | Invoke operation, retrying only the package's own interruption |
| `->assertAttempts(int)` | Assert the number of actual invocations |
| `->assertInterruptedAt(string, int $times = 1)` | Assert exact fault activation count |
| `->assertCheckpointHit(string, int)` | Assert exact checkpoint count |
| `->assertCompleted()` / `->value()` | Check success and inspect returned value |
| `FailureLab::replay(...)->times(int)->run(callable)` | Run bounded sequential duplicate deliveries |

A `FailureLabManager` is available from Laravel's service container for dependency-injected tests; the static entry point needs no bootstrapping.

## Project status

**Experimental source / not stable.** Real subprocess kill-and-resume, database cross-engine matrix, test-suite installation and a verified standalone GitHub/Packagist release are separate quality gates. The first focus is honest fault semantics and a useful public API. See [Release checklist](docs/RELEASE.md).

MIT licensed. Contributions welcome; see [CONTRIBUTING.md](CONTRIBUTING.md). Maintained by [@mykholy](https://github.com/mykholy).