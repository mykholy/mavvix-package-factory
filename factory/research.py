"""Deterministic collection and reporting; never claims a verified opportunity."""
import json
import re
from dataclasses import dataclass
from datetime import date, datetime, timezone
from pathlib import Path
from typing import Any, Protocol

from .models import Evidence, Opportunity, signal_score
from .relevance import relevance_reason

ID_PATTERN = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")


@dataclass(frozen=True)
class Topic:
    id: str
    title: str
    query: str
    title_keywords: tuple[str, ...] = ()


class IssuesClient(Protocol):
    def search_issues(self, query: str, *, per_page: int = 20) -> tuple[list[dict], bool]: ...


def load_topics(path: Path) -> list[Topic]:
    config = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(config, dict) or not isinstance(config.get("topics"), list):
        raise ValueError("Expected a JSON object containing topics array")
    if not 1 <= len(config["topics"]) <= 12:
        raise ValueError("Expected 1-12 topics")
    result: list[Topic] = []
    seen: set[str] = set()
    for entry in config["topics"]:
        if not isinstance(entry, dict):
            raise ValueError("Invalid topic entry")
        tid, title, query = (str(entry.get(k) or "").strip() for k in ("id", "title", "query"))
        if not ID_PATTERN.fullmatch(tid) or tid in seen:
            raise ValueError(f"Invalid/duplicate topic ID: {tid!r}")
        if not 5 <= len(title) <= 100 or not 5 <= len(query) <= 200:
            raise ValueError(f"Missing/oversized topic title or query: {tid}")
        if "updated:" in query.lower() or "is:pr" in query.lower():
            raise ValueError(f"Query contains unsupported qualifiers: {tid}")
        keywords = entry.get("title_keywords", [])
        if not isinstance(keywords, list) or len(keywords) > 12 or any(
            not isinstance(term, str)
            or not 2 <= len(term) <= 40
            or "\n" in term
            for term in keywords
        ):
            raise ValueError(f"Invalid title_keywords in {tid}")
        result.append(Topic(tid, title, query, tuple(term.strip().lower() for term in keywords)))
        seen.add(tid)
    return result


def scan(
    client: IssuesClient,
    topics: list[Topic],
    *,
    since: date,
    sleep_between: Any = None,
) -> tuple[list[Opportunity], list[str]]:
    """Latest activity is based on updated_at, not on when an issue was opened."""
    results: list[Opportunity] = []
    warnings: list[str] = []
    for index, topic in enumerate(topics):
        if index and sleep_between is not None:
            sleep_between(3.0)  # keep normal lexical search below GitHub's search rate limits
        query = f"{topic.query} is:issue updated:>={since.isoformat()}"
        issues, incomplete = client.search_issues(query, per_page=20)
        if incomplete:
            warnings.append(f"GitHub reports incomplete search results for {topic.id}")
        unique: dict[str, Evidence] = {}
        filtered = 0
        for raw in issues:
            evidence = Evidence.from_github(raw)
            if evidence is None or relevance_reason(raw, topic):
                filtered += 1
                continue
            unique.setdefault(evidence.url, evidence)
        selected = tuple(sorted(unique.values(), key=lambda e: (e.repository, e.number)))
        results.append(Opportunity(
            topic_id=topic.id,
            title=topic.title,
            issue_count=len(selected),
            distinct_repositories=len({e.repository.lower() for e in selected}),
            signal_score=signal_score(selected),
            evidence=selected,
            sampled_results=len(issues),
            filtered_results=filtered,
        ))
    return sorted(results, key=lambda x: (-x.signal_score, x.topic_id)), warnings


def _markdown_text(value: str) -> str:
    return " ".join(value.split()).replace("\\", "\\\\").replace("[", "\\[").replace("]", "\\]")


def report_markdown(report: dict[str, Any]) -> str:
    lines = [
        "# Mavvix — Developer Issue Discovery",
        "",
        f"Generated (UTC): {report['generated_at']}",
        f"Window start: {report['since']} (updated activity, not new issue creation)",
        f"Mode: {report['mode']}",
        "",
        "> **Unvalidated research signals.** These are GitHub search results, not proven",
        "> unmet demand. Scores (0–35) are prioritization hints, not release approvals.",
        "> Every candidate requires competitive research and human approval.",
        "> **Precision-first:** a title/topic keyword and Laravel context filter is applied",
        "> where configured. Filtered items may still be relevant; missing signals are",
        "> not evidence of missing demand. GitHub search only samples the latest results.",
        "",
    ]
    for warning in report["warnings"]:
        lines.append(f"- **Warning:** {_markdown_text(warning)}")
    for opportunity in report["opportunities"]:
        lines.extend([
            "",
            f"## {_markdown_text(opportunity['title'])}",
            "",
            f"- Topic: \`{opportunity['topic_id']}\`",
            f"- Signals: {opportunity['issue_count']} issues / {opportunity['distinct_repositories']} repositories",
            f"- Search sample: {opportunity.get('sampled_results', 0)} issues; filtered: {opportunity.get('filtered_results', 0)}; remainder may include duplicates",
            f"- Hint: {opportunity['signal_score']}/35; decision: **human review required**",
            "",
        ])
        if not opportunity["evidence"]:
            lines.append("No matching validated issue links in this scan.")
        else:
            for item in opportunity["evidence"]:
                lines.append(
                    f"- [{_markdown_text(item['title'])}]({item['url']})"
                    f" — \`{item['repository']}\`, {item['comments']} comments"
                )
    lines.extend([
        "",
        "## Required next steps",
        "",
        "1. Read original issue threads and verify the claimed pain point.",
        "2. Compare maintained alternatives, including existing Mavvix packages.",
        "3. Record independent sources and whether a package is the right solution.",
        "4. Complete the 100-point human-reviewed opportunity assessment.",
        "5. Open an engineering issue only if the evidence supports moving forward.",
        "",
    ])
    return "\n".join(lines)


def make_report(
    opportunities: list[Opportunity], warnings: list[str], since: date, mode: str
) -> dict[str, Any]:
    return {
        "schema_version": 2,
        "generated_at": datetime.now(timezone.utc).replace(microsecond=0).isoformat(),
        "since": since.isoformat(),
        "mode": mode,
        "warnings": warnings,
        "opportunities": [op.as_dict() for op in opportunities],
    }


def write_report(report: dict[str, Any], directory: Path) -> None:
    directory.mkdir(parents=True, exist_ok=True)
    (directory / "discovery.json").write_text(
        json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    (directory / "discovery.md").write_text(
        report_markdown(report) + "\n", encoding="utf-8"
    )
