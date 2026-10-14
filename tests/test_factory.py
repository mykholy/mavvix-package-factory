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
