# Release gates for developer packages

This is a **template** to apply to each future package, not proof that any package has passed.

## Design readiness
- Validated primary evidence and competitive alternatives, with review date
- Explicit users, failure mode, non-goals and acceptance criteria
- Small public API, ADR and stated PHP/Laravel support matrix
- License/security support and maintenance owner

## Engineering readiness
- Unit, integration and adverse-input tests covering claimed behavior
- Tests executed against every advertised PHP/Laravel combination where feasible
- Dependency advisories reviewed; static analysis and consistent style
- Robust failure modes, no leaked credentials or cross-tenant data
- Real usage fixture, reproducible examples and migration/upgrade path
- Performance claims backed by measured data and reproducible methodology

## Release candidate
- Reviewed PRs; CI passing on the exact commit
- Release changelog, versioning and backward-compatibility audit
- No high/critical unresolved security issues without an explicit documented decision
- Package name checked for conflicts and abandoned names
- Packagist metadata and tag plan checked

## Human approval
Only after the maintainer explicitly approves the exact revision and tag may a publish action occur. Creating a GitHub tag, GitHub Release, Packagist entry or 1.0.0 label is not itself a quality gate.

If a gate fails, stay in pre-release; provide an honest report with failing checks.
