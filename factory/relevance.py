"""Conservative heuristics for prioritizing public issue search results.

These filters deliberately trade recall for precision. They do not validate demand,
determine whether a bug is reproducible, or endorse a new package.
"""
import re
from typing import Any, Protocol


NOISE_TITLES = (
    "dependency dashboard",
    "[openspec]",
    "weekly standup",
)


class RelevantTopic(Protocol):
    title_keywords: tuple[str, ...]


def _word_present(text: str, keyword: str) -> bool:
    # 'retry' must not match 'retrying' unless both forms are configured.
    return re.search(r"(?<!\w)" + re.escape(keyword.lower()) + r"(?!\w)", text.lower()) is not None


def relevance_reason(issue: dict[str, Any], topic: RelevantTopic) -> str | None:
    """Return a reason to filter or None to keep for human review.

    Full-text matching is intentionally not trusted: GitHub can match enormous issue
    templates whose discussion is unrelated to the query. The title must match the
    specific engineering topic, and the issue must have a Laravel context.
    """
    title = str(issue.get("title") or "").strip().lower()
    if any(noise in title for noise in NOISE_TITLES):
        return "known_noisy_title"
    keywords = topic.title_keywords
    if keywords and not any(_word_present(title, term) for term in keywords):
        return "off_topic_title"
    if keywords:
        repo = str(issue.get("repository_url") or issue.get("html_url") or "").lower()
        body = str(issue.get("body") or "")[:2500].lower()
        if not (
            "laravel" in title
            or "laravel" in repo
            or _word_present(body, "laravel")
        ):
            return "missing_laravel_context"
    return None
