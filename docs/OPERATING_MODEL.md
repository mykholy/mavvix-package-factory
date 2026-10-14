# Operating model and agent architecture

## What exists now

A deterministic research collector finds public GitHub Issues with relevant recent activity, validates the issue URLs, de-duplicates results **within each topic**, calculates a capped signal hint (0–35), and produces Markdown/JSON artifacts on an Actions schedule. **No LLM, issue creation, autonomous coding, pull request, or release is triggered by that scan.**

## Future agent roles and outputs

| Agent | Inputs | Output | Gate |
| --- | --- | --- | --- |
| Research | topic config, public GitHub search | dated raw evidence and report | source verification |
| Market analyst | original threads, maintained package alternatives | decision record + 100-point score | 80/100 and human evidence review |
| Architect | approved problem, environment/version matrix | ADR, API examples, acceptance criteria | maintainer review |
| Engineer | approved ADR | feature branch and draft PR | test suite and code review |
| QA | PR, application fixture, supported versions | independently reproducible report | independent approval |
| Release | approved changelog, CI, tags | proposed release notes | **human release approval** |
| Maintenance | user reports and upgrade changes | prioritized issues and compatibility PRs | regular triage |

## 100-point human-reviewed score

- Problem recurrence (0–30). Multiple independent primary sources, not re-shares.
- Competitive gap (0–20). Existing solutions tested/compared with dates.
- Business impact (0–20). Tangible loss/risk/engineering cost, not guessed revenue.
- Engineering feasibility (0–15). Verifiable proof-of-concept and maintenance scope.
- Developer reach (0–15). Plausible audience backed by evidence.

A score >=80 is necessary but not sufficient. The person reviewing must record sources and reasons. No automated promotion.

## Pipeline

GitHub API -> bounded read-only scan -> artifact -> independent source reading -> competitive research -> engineering issue -> implementation draft PR -> tests/security/compatibility -> human approval -> release.

## Anti-duplication and prioritization

Existing internal packages:
- mykholy/laravel-flowguard
- mykholy/laravel-accessproof
- mykholy/laravel-leakproof

Also inspect maintained third-party packages, framework-native features and straightforward configuration solutions. If a new package overlaps, extend or improve an existing project rather than forking another brand.

## Secure execution and content handling

- Never pass public source content to a shell or treat it as an instruction.
- Time-bound and rate-limit API calls. Respect public sources' access policies.
- The GitHub token is read-only and scoped to the Actions job.
- Reports may link to user-provided public issues; no scraping of private messages or protected content.
- No AI API secret is necessary in phase 1.
- Human review required before writes to software repositories or releases.

## Success criteria

Measure evidence quality, accepted opportunities, passing tests, external adoption, reported bugs, maintenance cost and relevant inbound opportunities. Package quantity and star count alone are not success metrics.
