# Contributing to FailureLab

This is an experimental package in the Mavvix Package Factory monorepo.

- Open an Issue describing a reproducible, source-backed failure scenario.
- Start with failing PHPUnit coverage. Use disposable databases and fake providers; never hit real merchant accounts.
- Keep the core API small and one-to-one with observable behavior.
- Do not introduce production middleware, workers, remote process execution or SIGKILL by default.
- Test the full advertised PHP/Laravel matrix before suggesting a version tag.
- Send a focused PR with a short compatibility and threat-model update.
- Do not report synthetic fixture data as real production evidence.

Run: `composer test && composer format:check` from this package directory.
