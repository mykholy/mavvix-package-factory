# Architecture and threat model

## Explicit boundaries, not random chaos

The prototype deliberately does one thing: it injects exactly one **in-process PHP exception** on a configured occurrence of a named checkpoint during a CLI test.

**Core API:** `FailureLab::scenario(name)->failOnHit(checkpoint, index)` configures the plan; `Scenario::attempt(callable)` activates a narrow scoped runner; application code calls `FailureLab::checkpoint(name)` when the boundary occurs.

`FailureLab` stores at most one active scenario per Laravel application container. `finally` clears the scope after success, simulated failure or a real domain exception. Nested/concurrent scopes **within one container** are rejected, not silently interleaved.

`AttemptOutcome` records success or an injected fault. Real failures are rethrown to preserve signal and stack trace. The test explicitly decides when to replay; the library cannot infer retry safety.

## Security and failure containment

- Only CLI and Laravel's `testing` environment may create/execute scenarios.
- No HTTP entry points, global flags, backdoors, environment overrides, subprocess kills or file deletion.
- Outside an active scenario, checkpoints return without effects, including in production.
- Scenario names and checkpoint names are bounded and validated.
- All state is in-memory and local to the Laravel application container. No cross-process synchronization.
- Test environments may still have access to real cloud services. Developers **must use disposable credentials/fakes** before running fault tests. Environment checks alone do not sandbox third-party side effects.
- Production `require` is needed if your shipped service uses checkpoints; the inert path is intentionally tiny.

## Limitations that matter

- A thrown exception is NOT a process crash, a worker termination or a lost TCP packet.
- PHP exceptions can execute `finally` and Laravel rollback paths that are not run after `SIGKILL`.
- Redis/database job visibility, inter-process locks and races are **not** modeled.
- A local test against SQLite does not establish MySQL/PostgreSQL concurrency correctness.
- A fake gateway keyed by an idempotency value only proves that particular fake contract.
- No implication that all callback behaviors after failed DB COMMITs are covered.
- Does not detect missing application checkpoints automatically.

## Definition of done for beta

1. Evidence that current fault injection/concurrency tools fail to exercise a specific recovery use case.
2. Tests against real Laravel applications using disposable MySQL + PostgreSQL alongside SQLite.
3. A separate subprocess crash/recovery module only if safe fencing, isolated DB and complete cleanup can be guaranteed.
4. Scoped benchmarks and integration compatibility across PHP 8.3–8.5 and Laravel 12–13.
5. Independent maintainers able to write a passing replay test from the docs without assistance.

No automatic publishing or financial guarantees.
