"""Offline tests for autonomous but release-safe research workflows."""
import json
import tempfile
import unittest
from datetime import datetime, timezone
from pathlib import Path
from unittest.mock import Mock
from urllib.error import HTTPError

from factory.ai_triage import model_input, summarize
from factory.digest import GitHubIssuePublisher, digest_title, render_digest, safe_title, main


def report():
    return {
        "mode": "github_live",
        "schema_version": 2,
        "generated_at": "2026-10-14T01:03:10+00:00",
        "since": "2026-09-14",
        "warnings": [],
        "opportunities": [{
            "title": "Queue durability",
            "issue_count": 1,
            "sampled_results": 3,
            "filtered_results": 2,
            "signal_score": 7,
            "evidence": [{
                "url": "https://github.com/example/repo/issues/42",
                "title": "Laravel failed job retry @everyone",
            }],
        }],
    }


class FakeResponse:
    def __init__(self, data):
        self.data = data

    def __enter__(self):
        return self

    def __exit__(self, *_):
        return False

    def read(self, *_):
        return json.dumps(self.data).encode()


class DigestTests(unittest.TestCase):
    def test_weekly_id(self):
        self.assertEqual(digest_title(datetime(2026, 10, 14, tzinfo=timezone.utc)),
                         "[Research Digest] 2026-W42")

    def test_does_not_publish_fixtures(self):
        data = report()
        data["mode"] = "synthetic_fixture"
        with self.assertRaises(ValueError):
            render_digest(data, run_url="https://github.com/example/repo/actions/runs/1")

    def test_links_are_bounded_and_mentions_escaped(self):
        body = render_digest(report(), run_url="https://github.com/example/repo/actions/runs/1", analysis="@someone hypothesis")
        self.assertIn("unvalidated", body)
        self.assertNotIn("@everyone", body)
        self.assertNotIn("@someone", body)
        self.assertIn("https://github.com/example/repo/issues/42", body)

    def test_never_uses_other_sites_as_issue_evidence(self):
        data = report()
        data["opportunities"][0]["evidence"].append({
            "url": "https://evil.invalid/issues/5", "title": "Spoofed title"
        })
        body = render_digest(data, run_url="https://github.com/example/repo/actions/runs/1")
        self.assertNotIn("evil.invalid", body)

    def test_digest_shows_creation_timing_when_available(self):
        data = report()
        data["issue_creation_metrics"] = {
            "created_within_window": 2,
            "created_before_window": 4,
            "unknown_created_at": 1,
        }
        body = render_digest(data, run_url="https://github.com/example/repo/actions/runs/1")
        self.assertIn("Created earlier, updated in the window: 4", body)
        self.assertIn("not proof of demand", body)
        del data["issue_creation_metrics"]
        legacy = render_digest(data, run_url="https://github.com/example/repo/actions/runs/1")
        self.assertNotIn("New issues vs recently updated issues", legacy)

    def test_create_update_and_idempotency(self):
        existing = []
        calls = []
        def opener(request, timeout):
            calls.append((request.get_method(), request.full_url))
            if request.get_method() == "GET":
                return FakeResponse(existing)
            if request.get_method() == "POST":
                return FakeResponse({"html_url": "https://github.com/example/repo/issues/99"})
            return FakeResponse({"html_url": "https://github.com/example/repo/issues/7"})
        publisher = GitHubIssuePublisher("faketoken", "example/repo", opener=opener)
        self.assertEqual(publisher.upsert("Title", "Body")[0], "created")
        existing.append({"title": "Title", "number": 7, "html_url": "url", "body": "Body"})
        self.assertEqual(publisher.upsert("Title", "Body")[0], "unchanged")
        self.assertEqual(publisher.upsert("Title", "New body")[0], "updated")
        self.assertEqual([method for method, _ in calls], ["GET", "POST", "GET", "GET", "PATCH"])

    def test_dry_run_does_not_need_token(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "report.json"
            path.write_text(json.dumps(report()), encoding="utf-8")
            self.assertEqual(main([
                "--report", str(path),
                "--run-url", "https://github.com/example/repo/actions/runs/1",
                "--dry-run",
            ]), 0)


class AITriageTests(unittest.TestCase):
    def test_inference_input_is_bounded_and_unverified(self):
        value = model_input(report())
        data = json.loads(value)
        self.assertIn("unverified", data["disclaimer"])
        self.assertEqual(len(data["topics"]), 1)

    def test_rejects_fixtures(self):
        data = report()
        data["mode"] = "synthetic_fixture"
        with self.assertRaises(ValueError):
            model_input(data)

    def test_parses_mock_completion_without_real_inference(self):
        def opener(request, timeout):
            self.assertEqual(request.get_method(), "POST")
            self.assertIn("api.openai.com", request.full_url)
            return FakeResponse({"choices": [{"message": {"content": "Needs source verification."}}]})
        result = summarize(report(), "faketoken", opener=opener)
        self.assertIn("Needs source verification", result)


if __name__ == "__main__":
    unittest.main()
