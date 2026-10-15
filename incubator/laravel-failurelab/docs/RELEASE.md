# Release readiness checklist

This repo currently contains **experimental / pre-alpha source**.

- [ ] Composer dependency resolution independently checked for the tagged commit
- [ ] Full GitHub Actions PHP 8.3–8.5 / Laravel 12–13 matrix green
- [ ] PHPUnit unit and real Testbench integration suites green
- [ ] Pint and static analysis performed and issues addressed
- [ ] Example verified in an independently installed disposable Laravel application
- [ ] No claim of process crashes, exactly-once semantics or production safety
- [ ] API naming reviewed; backwards compatibility and semver documented
- [ ] Security and pathological inputs reviewed
- [ ] Package name / GitHub repo availability verified
- [ ] Real release manually reviewed with its commit hash
- [ ] Packagist connected to standalone repository, tag sync and install verification performed

Never create a stable release solely to obtain a badge. Use `v0.1.0` only after verifying the first usable integration.
