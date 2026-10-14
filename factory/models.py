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
        if "pull_request" in data:
            return None
        url = str(data.get("html_url") or "")
        parsed = urlsplit(url)
        parts = parsed.path.strip("/").split("/")
        if (
            parsed.scheme != "https"
            or parsed.hostname != "github.com"
            or len(parts) != 4
            or parts[2] != "issues"
            or not parts[3].isdigit()
            or not parts[0]
            or not parts[1]
        ):
            return None
        number = int(parts[3])
        if not number or int(data.get("number") or 0) != number:
            return None
        return cls(
            title=" ".join(str(data.get("title") or "(untitled)").split())[:240],
            url=url,
            repository=f"{parts[0]}/{parts[1]}",
            number=number,
            comments=max(0, int(data.get("comments") or 0)),
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
    decision: str = "human_review_required"

    def as_dict(self) -> dict[str, Any]:
        return asdict(self)


def signal_score(evidence: tuple[Evidence, ...]) -> int:
    """Ranking hint (0-35), NOT a market validation or 80/100 approval score."""
    repos = len({e.repository.lower() for e in evidence})
    comments = sum(e.comments for e in evidence)
    return min(35, min(15, 5 * repos) + min(10, 2 * len(evidence)) + min(10, comments // 3))
