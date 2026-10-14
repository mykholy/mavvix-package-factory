"""Offline deterministic checks. Fixture evidence is intentionally fabricated."""
import json
import tempfile
import unittest
from datetime import date
from pathlib import Path

from factory.cli import main
from factory.models import Evidence, signal_score
from factory.relevance import relevance_reason
from factory.research import Topic, load_topics, make_report, report_markdown, scan


def issue(number=12, repo="acme/project", title="An issue", comments=6):
    return {
        "html_url": f"https://github.com/{repo}/issues/{number}",
        "number": number,
        "title": title,
        "comments": comments,
        "created_at": "2026-10-01T00:00:00Z",
        "updated_at": "2026-10-03T00:00:00Z",
    }


class FakeClient:
    def search_issues(self, query, *, per_page=20):
        return [issue(), issue(), issue(13, title="[A] line\ninjection"), {
            **issue(14), "pull_request": {"url": "https://api.github.com/pulls/14"}
        }], False


class ModelsTests(unittest.TestCase):
    def test_rejects_malformed_and_pull_request_urls(self):
        self.assertIsNone(Evidence.from_github({**issue(), "html_url": "http://evil.test/x"}))
        self.assertIsNone(Evidence.from_github({**issue(), "number": 999}))
        self.assertIsNone(Evidence.from_github({**issue(), "pull_request": {}}))

    def test_score_is_bounded_and_not_a_market_score(self):
        evidence = tuple(Evidence.from_github(issue(n, f"org{n}/repo", comments=100)) for n in range(1, 40))
        self.assertEqual(signal_score(evidence), 35)


class ResearchTests(unittest.TestCase):
    def test_deduplicates_and_escapes_titles(self):
        opportunities, warnings = scan(
            FakeClient(), [Topic("test", "Test topic", "laravel reliability")],
            since=date(2026, 10, 1)
        )
        self.assertEqual(warnings, [])
        self.assertEqual(opportunities[0].issue_count, 2)
        self.assertEqual(opportunities[0].decision, "human_review_required")
        report = make_report(opportunities, warnings, date(2026, 10, 1), "synthetic_fixture")
        markdown = report_markdown(report)
        self.assertIn(r"\[A\] line injection", markdown)
        self.assertIn("Unvalidated research signals", markdown)

    def test_topics_reject_duplicates(self):
        with tempfile.TemporaryDirectory() as temp:
            file = Path(temp) / "topics.json"
            file.write_text(json.dumps({"topics": [
                {"id": "one", "title": "Valid title", "query": "laravel error"},
                {"id": "one", "title": "Valid title", "query": "laravel error"},
            ]}), encoding="utf-8")
            with self.assertRaises(ValueError):
                load_topics(file)

    def test_synthetic_cli_generates_both_artifacts(self):
        with tempfile.TemporaryDirectory() as temp:
            output = Path(temp) / "out"
            self.assertEqual(main([
                "--fixture", "tests/fixtures/search.json",
                "--output-dir", str(output)
            ]), 0)
            data = json.loads((output / "discovery.json").read_text(encoding="utf-8"))
            self.assertEqual(data["mode"], "synthetic_fixture")
            self.assertEqual(len(data["opportunities"]), 6)
            self.assertIn("no", "no opportunities auto-approved")
            self.assertTrue((output / "discovery.md").exists())



class GlobalMetricsTests(unittest.TestCase):
    def test_deduplicates_across_topics_and_within_topics(self):
        class Client:
            def search_issues(self, query, *, per_page=20):
                if query.startswith("laravel queue"):
                    return [issue(10), issue(10), issue(11)], False
                return [issue(11), issue(12)], False

        topics = [
            Topic("queue", "Queue reliability", "laravel queue"),
            Topic("tenancy", "Tenant isolation", "laravel tenancy"),
        ]
        opportunities, warnings = scan(Client(), topics, since=date(2026, 10, 1))
        report = make_report(opportunities, warnings, date(2026, 10, 1), "synthetic_fixture")
        self.assertEqual([op.issue_count for op in opportunities], [2, 2])
        self.assertEqual(report["global_metrics"], {
            "total_retained_issue_topic_matches": 4,
            "unique_issue_urls": 3,
        })
        self.assertIn("Unique GitHub Issue URLs (across topics): 3", report_markdown(report))
        self.assertIn("not independent developer demand", report_markdown(report))

    def test_empty_report_has_zero_global_counts(self):
        report = make_report([], [], date(2026, 10, 1), "synthetic_fixture")
        self.assertEqual(report["global_metrics"], {
            "total_retained_issue_topic_matches": 0,
            "unique_issue_urls": 0,
        })

    def test_legacy_report_without_global_metadata_renders(self):
        report = make_report([], [], date(2026, 10, 1), "synthetic_fixture")
        del report["global_metrics"]
        self.assertIn("Required next steps", report_markdown(report))


class RelevanceTests(unittest.TestCase):
    def test_title_matches_but_laravel_context_is_missing(self):
        topic = Topic("q", "Queue reliability", "query", ("queue", "retry"))
        self.assertEqual(
            relevance_reason(issue(title="Queue retries fail"), topic),
            "missing_laravel_context",
        )

    def test_laravel_context_and_topic_in_title_survive(self):
        topic = Topic("q", "Queue reliability", "query", ("queue", "retry"))
        matched = {**issue(title="Queue retry raises TypeError"), "body": "Laravel 12 retry fails"}
        self.assertIsNone(relevance_reason(matched, topic))

    def test_off_topic_and_dependency_dashboard_are_filtered(self):
        topic = Topic("q", "Queue reliability", "query", ("queue",))
        self.assertEqual(
            relevance_reason({**issue(title="Unrelated planning"), "body": "Laravel queue retry"}, topic),
            "off_topic_title",
        )
        self.assertEqual(
            relevance_reason({**issue(title="Dependency Dashboard for queue")}, topic),
            "known_noisy_title",
        )

    def test_live_style_topic_rejections_are_counted(self):
        class Client:
            def search_issues(self, query, *, per_page=20):
                return [
                    {**issue(1, title="Queue retry fails"), "body": "Laravel queue driver"},
                    {**issue(2, title="General changelog"), "body": "Laravel queue driver"},
                    {**issue(3, title="Queue retry fails"), "body": "Unrelated framework"},
                ], False
        topic = Topic("queue", "Queue reliability", "laravel queue", ("queue", "retry"))
        results, _ = scan(Client(), [topic], since=date(2026, 10, 1))
        self.assertEqual(results[0].issue_count, 1)
        self.assertEqual(results[0].sampled_results, 3)
        self.assertEqual(results[0].filtered_results, 2)
        self.assertEqual(results[0].decision, "human_review_required")


if __name__ == "__main__":
    unittest.main()
