"""Validated, bounded public evidence records and conservative triage hints."""
from dataclasses import asdict, dataclass
from typing import Any
from urllib.parse import urlsplit


@dataclass(frozen=True)
class Evidence:
    title: str
    url: str
    repository: str
    number: int
    comments: int
    created_at: str
    updated_at: str

    @classmethod
    def from_github(cls, data: dict[str, Any]) -> "Evidence | None":
        """Reject pull requests, malformed URLs, and non-issue API objects."""
        if not isinstance(data, dict) or "pull_request" in data:
            return None
        url = str(data.get("html_url") or "")
        try:
            parsed = urlsplit(url)
        except ValueError:
            return None
        parts = parsed.path.strip("/").split("/")
        if (
            parsed.scheme != "https"
            or parsed.netloc.lower() != "github.com"
            or parsed.query
            or parsed.fragment
            or len(parts) != 4
            or parts[2] != "issues"
            or not parts[3].isdigit()
            or not parts[0]
            or not parts[1]
        ):
            return None
        number = int(parts[3])
        # GitHub returns integer issue numbers. Reject malformed records instead
        # of letting one bad public search result abort the entire discovery run.
        if not number or type(data.get("number")) is not int or data["number"] != number:
            return None
        raw_comments = data.get("comments", 0)
        comments = max(0, raw_comments) if type(raw_comments) is int else 0
        return cls(
            title=" ".join(str(data.get("title") or "(untitled)").split())[:240],
            url=url,
            repository=f"{parts[0]}/{parts[1]}",
            number=number,
            comments=comments,
            created_at=str(data.get("created_at") or ""),
            updated_at=str(data.get("updated_at") or ""),
        )


@dataclass(frozen=True)
class Opportunity:
    topic_id: str
    title: str
    issue_count: int
    distinct_repositories: int
    signal_score: int
    evidence: tuple[Evidence, ...]
    sampled_results: int = 0
    filtered_results: int = 0
    decision: str = "human_review_required"

    def as_dict(self) -> dict[str, Any]:
        return asdict(self)


def signal_score(evidence: tuple[Evidence, ...]) -> int:
    """Ranking hint (0-35), NOT a market validation or 80/100 approval score."""
    repos = len({e.repository.lower() for e in evidence})
    comments = sum(e.comments for e in evidence)
    return min(35, min(15, 5 * repos) + min(10, 2 * len(evidence)) + min(10, comments // 3))
