"""Offline regression checks for bounded weekly research digest rendering."""
import unittest

from factory.digest import render_digest


RUN_URL = "https://github.com/mykholy/mavvix-package-factory/actions/runs/123"


def report(*, issue_count=7, metrics=None):
    return {
        "mode": "github_live",
        "schema_version": 2,
        "generated_at": "2026-11-18T00:00:00+00:00",
        "since": "2026-10-19",
        "warnings": [],
        "global_metrics": metrics if metrics is not None else {
            "total_retained_issue_topic_matches": 9,
            "unique_issue_urls": 7,
        },
        "opportunities": [{
            "title": "Laravel queue reliability",
            "issue_count": issue_count,
            "sampled_results": issue_count,
            "filtered_results": 0,
            "signal_score": 35,
            "evidence": [
                {
                    "title": f"Queue bug {number}",
                    "url": f"https://github.com/example/project/issues/{number}",
                }
                for number in range(1, issue_count + 1)
            ],
        }],
    }


class DigestVisibilityTests(unittest.TestCase):
    def test_discloses_truncated_sample_and_distinct_url_count(self):
        body = render_digest(report(), run_url=RUN_URL)
        self.assertIn("Retained issue-topic matches: 9", body)
        self.assertIn("Distinct sampled GitHub Issue URLs: 7", body)
        self.assertIn("Repeated matches across topics do not represent independent demand", body)
        self.assertIn("Showing 6 of 7 validated links", body)
        self.assertIn("Queue bug 6", body)
        self.assertNotIn("Queue bug 7", body)

    def test_six_or_fewer_links_are_not_called_truncated(self):
        body = render_digest(report(issue_count=6), run_url=RUN_URL)
        self.assertNotIn("Showing 6 of", body)
        self.assertIn("Queue bug 6", body)

    def test_malformed_urls_do_not_inflate_displayed_link_count(self):
        data = report(issue_count=7)
        data["opportunities"][0]["evidence"][6]["url"] = "https://example.org/bad"
        body = render_digest(data, run_url=RUN_URL)
        self.assertNotIn("Showing 6 of 7 validated links", body)
        self.assertNotIn("https://example.org/bad", body)

    def test_invalid_optional_metrics_are_not_rendered(self):
        for metrics in (
            {"total_retained_issue_topic_matches": True, "unique_issue_urls": 7},
            {"total_retained_issue_topic_matches": 9, "unique_issue_urls": -1},
            {"total_retained_issue_topic_matches": "9", "unique_issue_urls": 7},
        ):
            with self.subTest(metrics=metrics):
                body = render_digest(report(issue_count=0, metrics=metrics), run_url=RUN_URL)
                self.assertNotIn("## Across all topics", body)
                self.assertIn("## Samples by topic", body)


if __name__ == "__main__":
    unittest.main()
