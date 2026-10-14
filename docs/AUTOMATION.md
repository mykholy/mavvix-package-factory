# Hands-off operating mode

**Enabled by merging the supporting pull request:** the existing GitHub Actions job collects public GitHub Issues daily and produces a dated artifact. One best-effort GitHub Models inference per run synthesizes a small subset of source-linked issue *titles*. On Monday UTC, a separate restricted token creates or updates a weekly research digest Issue. The same publisher can run on manual dispatch.

## Security boundaries

- `discover`: `contents: read` and `models: read`, no write access.
- `weekly-digest`: `contents: read` and `issues: write`, no push/merge/tag/release access.
- Inference engine: GitHub Models `openai/gpt-4o`, using the built-in scoped GitHub Actions token. No custom API key required for this phase. API limits, account eligibility, settings and possible billing limits still apply.
- The agent only sees a bounded subset of linked issue titles, not verified issue content. Every AI section is **unverified**.
- No automatic creation of package code or production releases is enabled.
- Source text is never interpreted as executable instructions.
- Missing model access degrades gracefully: the raw research artifact and deterministic digest remain available; the model step reports a failure.

## Operational cadence

1. **Every day (05:17 UTC)**: generate and archive public GitHub issue research.
2. **Every Monday UTC** (plus manual dispatch): publish **one** idempotent digest Issue for that ISO week, never one Issue per weak signal.
3. The digest remains a lead backlog; engineering decisions require primary source validation and competitor analysis.
4. Human maintainer review remains necessary for sensitive actions (credentials, billing, production stable releases).

## Future stages

- External verified market sources (Packagist, Laravel/Reddit communities with compliant access).
- Stronger relevance scoring with independent primary threads and maintained-alternative comparisons.
- Sandboxed coding agents using an explicitly supported inference/coding engine.
- Draft PRs with real integration suites and version compatibility.
- Maintainer-approved stable publishing and ongoing issue maintenance.

This mode minimizes requests to the maintainer, but does not pretend to be an unattended engineering team with unlimited code/write privileges.
