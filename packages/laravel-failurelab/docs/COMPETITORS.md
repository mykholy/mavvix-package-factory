# Alternatives and positioning

This is an engineering comparison, not a claim that other packages are inferior.

| Tool | Actual focus | Why use it instead? |
| --- | --- | --- |
| Laravel `Queue::fake()` | Queue dispatch and interaction assertions | Unit/feature tests that don't need recovery replay |
| [Laravel Resilience](https://packagist.org/packages/me-shaon/laravel-resilience) | Faulting real container-managed dependencies; tests for fallback behavior | When you need dependency failure simulation without new checkpoints |
| [Race Lab](https://packagist.org/packages/race-lab/laravel-race-lab) | Deterministic multiple-worker race schedules with real processes | When concurrent ordering/locking correctness is your goal |
| [Bazooka](https://packagist.org/packages/laraveljutsu/bazooka) | Probabilistic or controlled chaos in controllers | When latency or exception resilience is more relevant |
| [Laravel FlowGuard](https://github.com/mykholy/laravel-flowguard) | Durable operation identity, leases and transactional outbox | When you need production-side ownership and recovery primitives |
| **FailureLab (this experiment)** | Explicit failure checkpoint, replay and invariant assertions | When you need a tiny deterministic replay experiment in a test |

**Decision:** This candidate is currently **experimental**; deeper competitive adoption testing may still show it's better to contribute to Laravel Resilience or Race Lab rather than publishing a separate package.

Source review: October 2026. Verify upstream features again before marketing.
