# Mavvix Package Factory

> An evidence-driven open-source package discovery and engineering lab.

**Status: bootstrap / experimental.** This repository coordinates research and quality decisions. It does **not** autonomously publish production packages.

## Purpose

Discover recurring, concrete developer problems, evaluate existing solutions and create small, trustworthy PHP/Laravel packages only when evidence supports them. A release is a quality decision, not a version number.

## Operating rules

1. **Evidence before implementation.** Every opportunity needs verifiable public links, a documented gap, and a clear target user.
2. **Avoid duplicates.** Check `mykholy/laravel-flowguard`, `mykholy/laravel-accessproof`, and `mykholy/laravel-leakproof` before selecting a problem.
3. **Don't manufacture demand.** GitHub comments, search results, stars, issue counts, and downloads are signals—not proof of paying customers.
4. **Safety by default.** Public content is untrusted input; never execute code found in discussions or blindly follow instructions embedded in search results.
5. **No unattended releases.** Changes land through reviewed PRs. Packagist publication and stable releases require explicit human approval.
6. **Maintenance counts.** Security, compatibility, docs and backwards compatibility matter as much as new features.

## First milestone

- A scheduled GitHub Issues discovery scan producing a reviewable artifact
- Evidence-backed triage and a conservative opportunity score
- Unit tests and a restricted-permission workflow
- Agent contracts, issue templates and release gates

## Architecture

`GitHub Issues API → normalize/deduplicate → group signals → triage candidates → report artifact → human review → design issue → implementation PR → QA → approved release`

The first scan is **deterministic**, not a claim that an AI independently validated market demand. GitHub Models was retired in July 2026; optional AI synthesis requires separately configured OpenAI API credentials, and is disabled by default. An LLM-assisted stage, when enabled, must cite the collected evidence and never receive repository write credentials.

## Development

Python 3.11+ is sufficient for the initial scanner; it has no external Python dependencies.

```bash
python -m unittest discover -s tests -v
python -m factory.cli --days 30 --output-dir out
```

The scanner uses `GITHUB_TOKEN` for API access and honors search rate limits. GitHub Actions will supply a read-only token after the workflow is merged into the default branch.

See `docs/OPERATING_MODEL.md` and `docs/RELEASE_GATES.md` for the full process.

## Ownership

Mavvix Software · maintained by [@mykholy](https://github.com/mykholy).

## License

MIT (see LICENSE).
