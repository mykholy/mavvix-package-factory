"""CLI for bounded discovery scans. No GitHub writes."""
import argparse
import json
import os
import sys
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path

from .github_api import GitHubAPIError, GitHubIssuesClient
from .research import load_topics, make_report, scan, write_report


class FixtureClient:
    """Synthetic input for local testing; never label results as real market evidence."""

    def __init__(self, filename: Path) -> None:
        data = json.loads(filename.read_text(encoding="utf-8"))
        if not isinstance(data, dict) or not isinstance(data.get("queries"), dict):
            raise ValueError("Fixture must contain queries object")
        self._data = data["queries"]

    def search_issues(self, query: str, *, per_page: int = 20) -> tuple[list[dict], bool]:
        lookup = query.split(" is:issue updated:>=", 1)[0]
        matches = self._data.get(lookup, [])
        if not isinstance(matches, list):
            raise ValueError("Fixture query entry must be an array")
        return matches[:per_page], False


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--days", type=int, default=30)
    parser.add_argument("--topics", type=Path, default=Path("research/topics.json"))
    parser.add_argument("--output-dir", type=Path, default=Path("artifacts"))
    parser.add_argument("--fixture", type=Path, help="Synthetic offline input for tests only")
    args = parser.parse_args(argv)
    if not 1 <= args.days <= 90:
        parser.error("--days must be between 1 and 90")
    try:
        topics = load_topics(args.topics)
        since = (datetime.now(timezone.utc) - timedelta(days=args.days)).date()
        client = FixtureClient(args.fixture) if args.fixture else GitHubIssuesClient(
            token=os.getenv("GITHUB_TOKEN", "")
        )
        opportunities, warnings = scan(
            client, topics, since=since,
            sleep_between=None if args.fixture else time.sleep,
        )
        report = make_report(opportunities, warnings, since, "synthetic_fixture" if args.fixture else "github_live")
        write_report(report, args.output_dir)
    except (GitHubAPIError, ValueError, OSError, json.JSONDecodeError) as exc:
        print(f"Research failed: {exc}", file=sys.stderr)
        return 1
    print(
        f"Wrote {args.output_dir}/discovery.md and discovery.json; "
        f"{sum(op.issue_count for op in opportunities)} issue-topic matches; "
        "no opportunities auto-approved."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
