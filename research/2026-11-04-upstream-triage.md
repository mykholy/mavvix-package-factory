# Laravel/PHP upstream triage — 2026-11-04

**Scope:** Source-linked research leads, not a product approval or a claim of reproduced bugs.
Read the primary GitHub issues and the public README files for
[FlowGuard](https://github.com/mykholy/laravel-flowguard),
[AccessProof](https://github.com/mykholy/laravel-accessproof), and
[LeakProof](https://github.com/mykholy/laravel-leakproof) on 2026-11-04.
Reported benchmarks and reproductions below have **not** been rerun independently.

| Primary evidence | Status observed 2026-11-04 | Existing approach / overlap | Decision |
| --- | --- | --- | --- |
| [Laravel #62253](https://github.com/laravel/framework/issues/62253), opened Oct 29 | Open. MySQL 8.4 deadlock on concurrent `throttleApi` requests; reporter links a [minimal reproduction](https://github.com/manstie/laravel-cache-deadlock-mre). | Laravel RateLimiter/cache-lock implementation and backend-specific tests own the behavior. FlowGuard's operation claims are not HTTP rate-limiter cache internals. | Reproduce upstream, assess cache driver/transaction configuration, then fix Laravel. **No package.** |
| [Laravel #62275](https://github.com/laravel/framework/issues/62275), opened Oct 31 | Open. Array-valued `channel_name` on `/broadcasting/auth` reportedly causes an unhandled `TypeError`/500 with Pusher; [reproduction repository](https://github.com/triwiboworidhopermana19/laravel-broadcasting-typeerror-repro). | Request type validation and broadcaster error handling belong in Laravel; AccessProof verifies authorization behavior, not malformed-request validation. | Add upstream validation regression; **no package**. |
| [Laravel #62212](https://github.com/laravel/framework/issues/62212), opened Oct 27 | Open, updated Nov 2. Reporter documents Redis tagged-cache flush cost proportional to keys × tags and a production 7.4 s Lua invocation; reproduction and timings are reporter evidence. | Native RedisTaggedCache flush needs bounded work and concurrency tests; FlowGuard/FailureLab cannot safely patch this internal behavior. | **Upstream fix**; test data correctness and concurrent writers before accepting per-tag deletion. |
| [Laravel #62112](https://github.com/laravel/framework/issues/62112), opened Oct 20 | Open. phpredis serializer/compression reportedly causes cache increment to return `false`, bypassing queue `maxExceptions`. Reporter references [PR #62106](https://github.com/laravel/framework/pull/62106). | Native queue worker + Redis serialization boundary; FlowGuard handles durable business operation claims, not Laravel worker exception counters. | Follow upstream tests and supported serializers; **no package**. |

## Existing products and alternatives

- **FlowGuard:** durable operation ownership, idempotency, verified-webhook inbox, transactional outbox, and reconciliation. It is not a replacement for Laravel's RateLimiter, broadcaster, RedisTaggedCache, or worker internals.
- **AccessProof:** test-only HTTP authorization, IDOR/BOLA and tenant isolation assertions. It does not perform arbitrary request schema validation.
- **LeakProof:** sensitive-field/secret detection in JSON API responses, not deadlock or malformed-request handling.
- **Laravel native features:** rate limiting, broadcasting authentication, cache tags and queue retry semantics own these failure surfaces. Prefer framework regressions and documented configuration mitigations.
- **[spatie/laravel-permission](https://github.com/spatie/laravel-permission):** role/permission administration, not a fix for broadcasting input types or cache deadlocks.

## Research precision follow-up

The [2026-W45 digest](https://github.com/mykholy/mavvix-package-factory/issues/32)
retained planning or dashboard issues alongside genuine engineering reports. Examples:
[Nexum-PSA #262](https://github.com/SveinT83/Nexum-PSA/issues/262) and
[WalidSite #390](https://github.com/walidatiyaai2025-gif/WalidSite/issues/390).
This is evidence of **false-positive risk**, not proof that every retained result is irrelevant.

The merged [PR #31](https://github.com/mykholy/mavvix-package-factory/pull/31)
separates distinct sampled issues created within the UTC window from older
recently updated issues. It does **not** fix topic-level semantic relevance.
Before changing filters, build a labeled fixture of actual retained/rejected
examples and measure precision **and** false negatives; do not inflate a
35-point heuristic into validated market demand.

**Decision: NO NEW PACKAGE.** Revisit only with independent reproduction,
multiple distinct user reports, a demonstrated gap in maintained alternatives,
and human-reviewed opportunity scoring. No stable release or Packagist action.
