# FailureLab pre-alpha pilot

**Scope:** The experimental standalone Composer package source lives in
[`incubator/laravel-failurelab/`](../incubator/laravel-failurelab/).

**Goal:** Verify that explicit failure boundary replay tests provide enough
value beyond Laravel native testing, Laravel Resilience and Race Lab to justify
an independent package. This is not automatic approval for Packagist or stable
publication.

## Verifiable engineering gates

- [x] All four PHP/Laravel CI combinations pass (incubator CI); live host-app validation is still pending.
- [ ] Independent reviewer reproduces both checkout and webhook tests.
- [ ] Document a genuine customer problem and a source-backed competitor gap.
- [ ] Record three independent trial users or equivalent source-based evidence.
- [x] Test-only callback checkpoints; no instrumentation dependency required in production source.
- [ ] Decide whether to move to a dedicated public repository or to contribute
      upstream instead.

## Adoption before release

The package can be tried as a Composer path repository; there is currently no
supported automatic conversion of this subtree into a new GitHub repository.
Neither a release tag nor a Packagist publication is present. Never claim that
the CI tests prove true SIGKILL recovery, financial exactly-once semantics or
distributed lock correctness.
