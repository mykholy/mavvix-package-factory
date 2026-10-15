# FailureLab pre-alpha pilot

**Scope:** The experimental standalone Composer package source lives in
[`packages/laravel-failurelab/`](../packages/laravel-failurelab/).

**Goal:** Verify that explicit failure boundary replay tests provide enough
value beyond Laravel native testing, Laravel Resilience and Race Lab to justify
an independent package. This is not automatic approval for Packagist or stable
publication.

## Verifiable engineering gates

- [ ] All four PHP/Laravel CI combinations pass.
- [ ] Independent reviewer reproduces both checkout and webhook tests.
- [ ] Document a genuine customer problem and a source-backed competitor gap.
- [ ] Record three independent trial users or equivalent source-based evidence.
- [ ] Validate production-code instrumentation footprint and testing guard behavior.
- [ ] Decide whether to move to a dedicated public repository or to contribute
      upstream instead.

## Adoption before release

The package can be tried as a Composer path repository; there is currently no
supported automatic conversion of this subtree into a new GitHub repository.
Neither a release tag nor a Packagist publication is present. Never claim that
the CI tests prove true SIGKILL recovery, financial exactly-once semantics or
distributed lock correctness.
