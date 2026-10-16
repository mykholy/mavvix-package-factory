# Design choices and comparison

## Narrow, explicit dependency contract

The operation receives a `Mykholy\FailureLab\Contracts\Checkpoint` argument. This is a test seam that may be provided by a test wrapper or the host application's own injected hook. It is not required in ordinary production business code; a host may pass a no-op implementation outside tests if it chooses to expose explicit checkpoints.

No global state, facades, database tables, production middleware or queue interceptors are necessary. The `Scenario` and `Replay` builders return cloned instances when configured. Reports are immutable snapshots, and exceptions carry only a sanitized point and attempt.

## What the library guarantees

1. A named checkpoint is interrupted once on its specified attempt, provided the caller actually reaches it.
2. The bounded runner retries only its own `InjectedFailure` exception.
3. The report records which attempt completed/interrupted/failed and how often each checkpoint was hit. `assertFaultPlanSatisfied()` additionally checks that every configured fault was actually triggered; tests can otherwise silently skip a breakpoint after refactoring. Assert final completion and business invariants separately.
4. The replay runner calls the handler sequentially up to the requested delivery count, **stopping at the first unexpected error by default**. An opt-in `continueAfterFailure()` models subsequent delivery attempts without simulating transport or process termination. `assertFailedDeliveries()` and `assertRecoveredAfterFailure()` are execution assertions; a business invariant must be checked separately.
5. Runtime rejects execution unless `APP_ENV=testing`.

## What it does not guarantee

- Capturing/detecting real worker SIGKILL, OOM, OS crashes or network partitions.
- Distributed transaction, exactly-once provider acceptance or exactly-once webhook delivery.
- Reproducible timing of real concurrency and database lock acquisition.
- The correctness of fake providers, host application cleanup or the actual business invariant.

## Existing tools and overlaps

- Laravel's `Queue::fake()`, `Bus::fake()` and HTTP test helpers: sufficient for ordinary dispatch or request assertions.
- `race-lab/laravel-race-lab`: deterministic **parallel** worker checkpoints for concurrency, not the same as a short sequential failure/retry test.
- `laraveljutsu/bazooka`: application-level latency and exceptions; test harness is opt-in and has assertions for recovery attempts.
- `mykholy/laravel-flowguard`: production operation fencing/outbox/inbox; not a test runner. FailureLab can test host integrations with FlowGuard, but does not reproduce it or depend on it.

## Next phase, if demand is validated

1. Real process-isolated SIGTERM/SIGKILL test driver with explicit allowlists, ephemeral database and a supervisor; clearly distinct from synchronous injection.
2. MySQL and PostgreSQL integration fixture to validate commit boundary behavior without wrapped PHPUnit transactions.
3. Provider-neutral fake HTTP gateway fixture with key-based idempotency and unknown-outcome simulations.
4. Host app demonstration with documented limitations, mutation tests and versioned public contracts.

All of those are roadmap candidates, not features claimed by the current source.
