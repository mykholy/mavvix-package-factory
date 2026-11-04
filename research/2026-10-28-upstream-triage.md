# PHP/Laravel upstream triage — 2026-10-28

**Method:** Read the primary GitHub issue threads and the public README files of
[FlowGuard](https://github.com/mykholy/laravel-flowguard),
[AccessProof](https://github.com/mykholy/laravel-accessproof),
[LeakProof](https://github.com/mykholy/laravel-leakproof) and
[FailureLab](https://github.com/mykholy/laravel-failurelab).
Reported reproductions and performance figures below are **maintainer/reporter claims**;
they were **not independently reproduced by this review**.

## Evidence-backed issues, not automatic product opportunities

| Primary source | Observed 2026-10-28 | Existing solution / next action | Decision |
| --- | --- | --- | --- |
| [Laravel #62212](https://github.com/laravel/framework/issues/62212), opened Oct 27 | Open. Reporter describes Redis tagged-cache flush running up to 1,000 keys × thousands of tags in a blocking Lua script; cites 7.4 s production SLOWLOG and a local benchmark. A second commenter reports an independent reproduction on Oct 27. | Review upstream implementation and concurrency correctness of per-tag flush; [Laravel Redis cache](https://laravel.com/docs/13.x/cache) already provides the native API. | **Upstream fix / regression tests**, not a wrapper package. |
| [Laravel #62112](https://github.com/laravel/framework/issues/62112), opened Oct 20 | Open. Reporter shows phpredis serializer/compression causing cache increment to return false, undermining queue `maxExceptions`. | [PR #62106](https://github.com/laravel/framework/pull/62106) proposed a native fix and tests but was closed unmerged. Follow maintainers and test supported serializer combinations. | **Upstream**, no new package. |
| [Laravel #61829](https://github.com/laravel/framework/issues/61829), opened Oct 2 | Open. Docker/SQL Server reproduction claims a failed nested savepoint rollback can leave Laravel's transaction level stale and later writes uncommitted. | Driver-specific framework recovery/error propagation and integration regression tests. | **Upstream**, no general Laravel package. |
| [Laravel #61964](https://github.com/laravel/framework/issues/61964), opened Oct 11 | **Closed as completed on Oct 27** by a maintainer. The two linked proposals [#61968](https://github.com/laravel/framework/pull/61968) and [#62082](https://github.com/laravel/framework/pull/62082) were closed **without merge** when checked. | Verify the actual upstream code/commit before asserting a fix shipped; a closed issue alone is not release evidence. | **Track upstream**, no new package. |

## Overlap and competitive gap

- **FlowGuard**: production durable operation ownership, idempotency, inbox/outbox and reconciliation. Relevant to duplicate side effects and retries, but not a replacement for Laravel's transaction-manager or Redis cache internals.
- **AccessProof**: test-only authorization/IDOR and cross-tenant access assertions. Not relevant to these four framework-internal defects.
- **LeakProof**: sensitive JSON response and secret exposure detection. Not relevant to queue/cache correctness.
- **FailureLab**: test-only deterministic fault/replay assertions; useful to demonstrate recovery contracts, but does not patch framework internals. [Race Lab](https://packagist.org/packages/race-lab/laravel-race-lab) addresses concurrent checkpoints, while [Laravel queue fakes](https://laravel.com/docs/13.x/queues#testing) cover native dispatch assertions.
- **Laravel native features**: queue `after_commit`, cache tags, retries, and transactions already define the relevant public contracts. Fixes should preferentially land in the owning framework.

## Scanner quality observations

[2026-W44 digest](https://github.com/mykholy/mavvix-package-factory/issues/29)
scored queue reliability **35/35** while retaining several issue titles not clearly
about a reusable Laravel problem. Its search window uses `updated:`, so an old
issue resurfacing is not new demand. This review proposes a separate UTC creation-date
classification for **distinct sampled URLs**, without interpreting either count or
heuristic score as market size.

**Decision: NO NEW PACKAGE.** Reassess only if multiple independent, current
reports establish a reusable gap not already covered by maintained upstream fixes
or existing packages. No stable release or Packagist action is authorized.
