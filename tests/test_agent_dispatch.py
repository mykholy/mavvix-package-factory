"""Offline tests for owner-gated Copilot assignment. No network requests."""
import json
import unittest

from factory.agent_dispatch import (
    DispatchError, GitHubAPI, dispatch, eligibility,
)


def record():
    return {
        "number": 9, "state": "open", "user": {"login": "mykholy"},
        "labels": [{"name": "factory-maintenance"}, {"name": "agent-approved"}],
        "assignees": [],
    }


class FakeAPI:
    def __init__(self, issue=None):
        self.issue = issue or record()
        self.calls = []

    def get_issue(self, number):
        self.calls.append("get")
        return self.issue

    def assign(self, number):
        self.calls.append("assign")
        return {**self.issue, "assignees": [{"login": "copilot-swe-agent"}]}

    def mark(self, number):
        self.calls.append("mark")


class Response:
    def __init__(self, data):
        self.data = data

    def __enter__(self):
        return self

    def __exit__(self, *_):
        return False

    def read(self, *_):
        return json.dumps(self.data).encode()


class AgentDispatchTests(unittest.TestCase):
    def call(self, api, **kwargs):
        return dispatch(api, number=9, repo="mykholy/mavvix-package-factory",
                        actor="mykholy", enabled=True, **kwargs)

    def test_assigns_and_marks(self):
        api = FakeAPI()
        self.assertEqual(self.call(api), "dispatched")
        self.assertEqual(api.calls, ["get", "assign", "mark"])

    def test_missing_credential_never_assigns(self):
        api = FakeAPI()
        self.assertEqual(dispatch(
            api, number=9, repo="mykholy/mavvix-package-factory",
            actor="mykholy", enabled=False,
        ), "disabled")
        self.assertEqual(api.calls, ["get"])

    def test_marked_issue_is_idempotent(self):
        item = record()
        item["labels"].append({"name": "agent-dispatched"})
        api = FakeAPI(item)
        self.assertEqual(self.call(api), "already_dispatched")
        self.assertEqual(api.calls, ["get"])

    def test_existing_assignment_is_reconciled_without_new_task(self):
        item = record()
        item["assignees"] = [{"login": "copilot-swe-agent[bot]"}]
        api = FakeAPI(item)
        self.assertEqual(self.call(api), "reconciled")
        self.assertEqual(api.calls, ["get", "mark"])

    def test_rejects_unsafe_issues(self):
        for key, value in [
            ("state", "closed"),
            ("user", {"login": "other"}),
            ("labels", [{"name": "factory-maintenance"}]),
            ("pull_request", {"url": "https://api.github.com/pulls/9"}),
        ]:
            with self.subTest(key=key):
                item = record()
                item[key] = value
                api = FakeAPI(item)
                with self.assertRaises(DispatchError):
                    self.call(api)
                self.assertEqual(api.calls, ["get"])

    def test_only_owner_and_factory_repo(self):
        with self.assertRaises(DispatchError):
            eligibility(record(), repo="another/repo", actor="mykholy")
        with self.assertRaises(DispatchError):
            eligibility(record(), repo="mykholy/mavvix-package-factory", actor="bot")

    def test_mismatched_issue_number(self):
        api = FakeAPI({**record(), "number": 10})
        with self.assertRaises(DispatchError):
            self.call(api)

    def test_failed_assignment_does_not_mark(self):
        class BadAPI(FakeAPI):
            def assign(self, number):
                self.calls.append("assign")
                return self.issue
        api = BadAPI()
        with self.assertRaisesRegex(DispatchError, "not confirmed"):
            self.call(api)
        self.assertEqual(api.calls, ["get", "assign"])

    def test_separate_read_and_assignment_tokens(self):
        requests = []
        def opener(req, timeout):
            requests.append((
                req.get_method(), req.full_url,
                req.headers.get("Authorization"),
                json.loads(req.data) if req.data else None,
            ))
            if req.get_method() == "GET":
                return Response(record())
            if req.full_url.endswith("/assignees"):
                return Response({**record(), "assignees": [{"login": "copilot-swe-agent"}]})
            return Response([{"name": "agent-dispatched"}])
        api = GitHubAPI("mykholy/mavvix-package-factory",
                        "read-token", "user-token", opener=opener)
        self.assertEqual(self.call(api), "dispatched")
        self.assertEqual([r[0] for r in requests], ["GET", "POST", "POST"])
        self.assertEqual(requests[0][2], "Bearer read-token")
        self.assertEqual(requests[1][2], "Bearer user-token")
        self.assertEqual(requests[1][3]["agent_assignment"]["target_repo"],
                         "mykholy/mavvix-package-factory")


if __name__ == "__main__":
    unittest.main()
