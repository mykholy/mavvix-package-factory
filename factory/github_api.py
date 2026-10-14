"""Read-only GitHub Issues API client with bounded retries."""
import json
import time
from typing import Any, Callable
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen


class GitHubAPIError(RuntimeError):
    """An unsuccessful or untrustworthy API response."""


class GitHubIssuesClient:
    def __init__(
        self,
        token: str = "",
        *,
        opener: Callable[..., Any] = urlopen,
        sleeper: Callable[[float], None] = time.sleep,
    ) -> None:
        self._token = token
        self._opener = opener
        self._sleeper = sleeper

    def search_issues(self, query: str, *, per_page: int = 20) -> tuple[list[dict], bool]:
        if not 1 <= per_page <= 100:
            raise ValueError("per_page must be between 1 and 100")
        url = "https://api.github.com/search/issues?" + urlencode({
            "q": query,
            "per_page": per_page,
            "sort": "updated",
            "order": "desc",
        })
        headers = {
            "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": "2022-11-28",
            "User-Agent": "mavvix-package-factory-research",
        }
        if self._token:
            headers["Authorization"] = f"Bearer {self._token}"
        request = Request(url, headers=headers, method="GET")
        for attempt in range(3):
            try:
                with self._opener(request, timeout=20) as response:
                    payload = json.load(response)
                if not isinstance(payload, dict) or not isinstance(payload.get("items"), list):
                    raise GitHubAPIError("Unexpected GitHub Search API response")
                items = [item for item in payload["items"] if isinstance(item, dict)]
                return items, bool(payload.get("incomplete_results", False))
            except HTTPError as exc:
                # A token with no remaining quota is not fixed by short retries.
                if exc.code == 403 and exc.headers.get("X-RateLimit-Remaining") == "0":
                    raise GitHubAPIError("GitHub search quota exhausted") from exc
                if exc.code not in (429, 500, 502, 503, 504) or attempt == 2:
                    raise GitHubAPIError(f"GitHub Search HTTP {exc.code}") from exc
                self._sleeper(2 ** attempt)
            except (URLError, TimeoutError) as exc:
                if attempt == 2:
                    raise GitHubAPIError("GitHub Search network failure") from exc
                self._sleeper(2 ** attempt)
        raise GitHubAPIError("GitHub Search retries exhausted")
