# FailureLab release readiness

**Status: pre-alpha source (not published on Packagist or in a standalone repository).**
Last review: 2026-10-16. The main factory source is authoritative until a
dedicated package repository exists.

## Verified gates

- [x] Dependency-free smoke tests on PHP 8.3, 8.4 and 8.5
- [x] PHPUnit / Orchestra Testbench matrix: PHP 8.3 + Laravel 12; PHP 8.4 + Laravel 12/13; PHP 8.5 + Laravel 13
- [x] PHPUnit fails on risky tests, and the suite contains explicit recovery negative controls
- [x] Laravel Pint `--test` enforces the project's PHP style
- [x] Clean Laravel 12 and 13 consumer applications install via Composer path repository, resolve the provider through package discovery, and execute a Scenario and Replay
- [x] The canonical source archive is built and verified by GitHub Actions
- [x] Documentation disclaims real process termination, network exactly-once guarantees and automatic protection of production data

Evidence: [fresh Composer installation + test matrix](https://github.com/mykholy/mavvix-package-factory/actions/runs/38831465896),
[package source-archive pipeline](https://github.com/mykholy/mavvix-package-factory/actions/runs/38831215982).
The later CI fixes are tracked in [PR #22](https://github.com/mykholy/mavvix-package-factory/pull/22) and
[PR #23](https://github.com/mykholy/mavvix-package-factory/pull/23).

## Open release gates

- [ ] Dedicated PHPStan/Larastan or equivalent static analysis gate with a clean baseline
- [ ] Real MySQL and PostgreSQL integration tests, especially transaction/error boundaries
- [ ] Mutation/negative testing for business invariant checks and unexpected provider state
- [ ] Security audit including source scanning and reviewed unsafe-environment behavior
- [ ] External maintainer adoption feedback and competitive-gap validation (the initial opportunity score did not clear the factory's 80/100 gate)
- [ ] Public API / semantic-versioning review and changelog for the exact candidate commit
- [ ] Standalone GitHub repository `mykholy/laravel-failurelab` created with proper read/write permissions and identical tested source
- [ ] Tagged release candidate verified from the standalone repository (not a developer machine or path checkout)
- [ ] Packagist submitted and `composer require --dev mykholy/laravel-failurelab` verified on a clean, independent project
- [ ] Human approval of the exact release SHA and version

## Publication policy

Do **not** create a stable tag to make the package look complete. This is a
**test harness**, not a fault-tolerant transaction coordinator or worker supervisor.

The first suitable milestone after the remaining gates is a reviewed pre-release
(e.g. `v0.1.0-beta.1`), followed by documented developer feedback and fixes
before any stable `v1.0.0`. Never publish directly from this incubator.
