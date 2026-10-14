# Research topics

Edit `topics.json` to propose up to twelve distinct search topics. Each query uses GitHub's Issues Search syntax.

The scheduled scanner appends `is:issue updated:>=YYYY-MM-DD` and asks for at most 20 results per topic. Use narrow terms and **manually assess recall and false positives**.

The 0–35 number is only an ordering hint. A score of zero or a missing topic is **not** proof that demand is absent. A high score is **not** a verified opportunity.

## Precision safeguards (phase 2)

Every configured topic can specify `title_keywords`. The scanner retains a result only when its title matches a configured term **and** it has a Laravel context signal. Searches also use GitHub's `language:PHP` filter. Known noisy titles are excluded. The report records how many hits were sampled and filtered.

These are deliberately conservative heuristics. They can miss valid issues and cannot prove demand or competitors' deficiencies. Always read original discussions and the [first-run audit](2026-10-14-first-run-audit.md) before promoting a candidate.

The scanner only samples the latest **20** matches per topic; it does **not** exhaustively enumerate GitHub Issues. Never interpret filtered counts as all irrelevant GitHub issues.

## Global deduplication metrics

New discovery reports include `global_metrics.total_retained_issue_topic_matches` (sum of retained per-topic counts) and `global_metrics.unique_issue_urls` (case-normalized GitHub Issue URL count after validation/filtering, deduplicated across topics). Existing report fields are preserved for downstream clients. The Markdown report surfaces both metrics.

Because scans sample the latest 20 results per topic, neither count measures all related GitHub problems, distinct customers, or validated market demand.
