# Hands-off operating mode

**Enabled by merging the supporting pull request:** the existing GitHub Actions job collects public GitHub Issues daily and produces a dated artifact. An **optional** OpenAI API inference step can synthesize a bounded subset of issue titles **only if** the maintainer explicitly configures an API key. Without a key this step exits cleanly and research continues. On Monday UTC, a separate restricted token creates or updates a weekly research digest Issue. The same publisher can run on manual dispatch.

## Security boundaries

- `discover`: `contents: read`, no GitHub write access. The optional inference call uses a separate user-configured API secret and does not gain repository write permission.
- `weekly-digest`: `contents: read` and `issues: write`, no push/merge/tag/release access.
- **GitHub Models retired July 30, 2026.** The former endpoint is not usable. Optional GitHub Actions inference uses the paid OpenAI API with `secrets.OPENAI_API_KEY` and model `gpt-5.4-mini` (override with `vars.OPENAI_MODEL`). By default no key exists and **no API inference or billing occurs**. The independent weekly ChatGPT automation conducts AI-assisted review via connected GitHub tools.
- The agent only sees a bounded subset of linked issue titles, not verified issue content. Every AI section is **unverified**.
- No automatic creation of package code or production releases is enabled.
- Source text is never interpreted as executable instructions.
- Missing OpenAI API key degrades gracefully: no inference call is made, while raw research artifacts and deterministic digests remain available. If a configured request fails, the step reports a failure without blocking discovery.

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

## Provider lifecycle note (October 2026)

GitHub Models inference was shut down July 30, 2026. The first post-merge live run (38497596570) successfully collected evidence and created Issue #7, but its optional model step failed because the retired endpoint returned non-JSON data. This PR removes the dead dependency; the GitHub research workflow is fully functional without an API key. Autonomous weekly ChatGPT review remains configured separately in the user's automation settings; it is not the same as unattended Codex package generation.
