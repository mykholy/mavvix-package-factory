# Safety and threat model

## Scope

FailureLab is an **in-process deterministic exception/replay harness**. It does not terminate a PHP process, replace a queue worker, intercept arbitrary execution, or verify actual crash safety. A PHPUnit test's callback is run explicitly on the test process. The application under test owns its database transactions, fake provider, idempotency state, and cleanup.

## Non-negotiable constraints

- Install as `require-dev`. Refuse to start `Scenario::run()` and `Replay::run()` unless `APP_ENV=testing` in the current process.
- Never use real payment provider credentials, live webhooks, production Redis, or production databases in tests. An `APP_ENV=testing` setting **alone does not prove** data sources are disposable.
- A failure inserted after provider acceptance is not the same as an external request timing out; the caller must provide a fake provider with explicit behavior and separate unknown-outcome handling.
- Injected exceptions are retried by the harness only; any other application exception stops the scenario. Failed runs are visible in the returned report.
- There is no automatic rollback between attempts. Use test DB teardown; remember that transaction-scoped PHPUnit tests can mask real commit-boundary failures.
- Sequential replay cannot find cross-process race conditions or establish distributed exactly-once delivery. Use process-isolated tests and real databases to prove those properties.
- Checkpoint names are constrained to safe bounded ASCII identifiers; scenario names reject control characters.
- No automatic mutation of global exception handlers, Laravel `Queue`, production providers or process state.

## Example of a dangerous false conclusion

A payment test that mocks provider success on every retry might pass after two captures unless the test asserts exactly one accepted remote capture and the same stable idempotency key. Never assert only the local order status.

## Test failure semantics

`ScenarioReport::assertCompleted()` checks the final attempt. It is possible to complete without ever triggering the configured failure if the checkpoint is skipped: therefore explicitly call `assertInterruptedAt()` and `assertCheckpointHit()`.

## Incident and vulnerability reporting

Do not publish a working exploit or customer credentials in a GitHub issue. Prefer private vulnerability reporting or a private message to the maintainer.


## Error-report redaction and test environment

Scenario and replay assertions intentionally omit raw exception messages, which can contain HTTP bodies, tokens or private API responses. Report `summary()` includes only attempt status and checkpoint counters. Assertion names should remain non-sensitive.

Execution requires **PHP CLI** and exactly `APP_ENV=testing`; this is a defense against accidental web invocation, **not proof** that your database and external services are safe. Always use disposable test dependencies.
