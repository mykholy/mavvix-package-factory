# Runbook

## 1. First-time setup
- Merge the bootstrap PR into `main` after reviewing it. Scheduled workflows operate from the **default branch**.
- Ensure GitHub Actions is enabled under the repository settings.
- No custom repository secret is needed for the first discovery scan. Actions supplies `github.token` with **contents: read**.
- Set required CI checks and review rules using repository branch protection/rulesets if available for your plan.

## 2. Execute a scan

In GitHub: **Actions -> Daily developer issue discovery -> Run workflow**. Download the `issue-discovery-<run_id>` artifact and open `discovery.md`.

Or locally:

```bash
export GITHUB_TOKEN=your_local_read_only_token
python -m factory.cli --days 30 --output-dir artifacts
```

Do not commit a token or report with private material.

## 3. Review results

GitHub search can be incomplete. Activity uses the `updated` timestamp, so an old issue can appear. This **does not** mean a newly reported problem or confirmed unmet need. Review original threads and compare alternative packages.

## 4. Promote manually
Open a new Opportunity issue from the template and record source-backed 100-point assessment. Do not create package repositories for weak, duplicate or insufficiently validated signals.

## 5. Troubleshooting
- **No workflow:** it must be merged into the default branch; Actions must be enabled.
- **403 / quota exhausted:** use a valid read-only token or reduce queries; no short retry bypasses hard quota.
- **Empty report:** adjust topic queries, window or language, then confirm sources directly.
- **Failed CI:** run `python -m unittest discover -s tests -v` and review Actions logs.
- **Reports not found:** check job status and uploaded artifact retention (30 days).

## 6. Known limitations
- Research source is GitHub Issues only, not Reddit/Stack Overflow/Packagist.
- No semantic clustering or trust model for source authors.
- No real market validation or 80-point approval is computed by software.
- No autonomous solution construction or publishing is configured yet.
- Public issues are not a representative sample of companies' procurement priorities.
