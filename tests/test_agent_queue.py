"""Safety and scheduling controls for autonomous Codex changes."""
import json
import subprocess
import tempfile
import unittest
from pathlib import Path

from factory.agent_queue import (
    export_patch, render_task, select_issue, validate_paths,
)


def issue(number=9, author="mykholy", title="[Agent Task] Increase validation coverage"):
    return {
        "number": number,
        "author": {"login": author},
        "title": title,
        "body": "## Scope\nIncrease unit test coverage for this tool.\n\n"
                "## Acceptance criteria\nInclude negative cases and ensure all existing tests pass.\n",
    }


class SelectionTests(unittest.TestCase):
    def test_only_owner_authored_bound_tasks_are_eligible(self):
        result = select_issue([
            issue(10, author="stranger"),
            issue(11, title="Generic public issue"),
            issue(12),
        ], [])
        self.assertEqual(result["number"], 12)

    def test_previously_processed_branch_does_not_repeat(self):
        result = select_issue([issue(12)], [
            {"headRefName": "agent/issue-12-20261014-1234"}
        ])
        self.assertIsNone(result)

    def test_missing_acceptance_criteria_is_not_ready(self):
        task = issue(14)
        task["body"] = "x" * 100
        self.assertIsNone(select_issue([task], []))

    def test_task_prompt_marks_issue_untrusted(self):
        prompt = render_task(issue())
        self.assertIn("<untrusted_issue>", prompt)
        self.assertIn("NEVER read credentials", prompt)
        self.assertIn("DRAFT PR", prompt)


class ScopeTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.repo = Path(self.temp.name)
        subprocess.run(["git", "init", "-q", str(self.repo)], check=True)
        subprocess.run(["git", "-C", str(self.repo), "config", "user.name", "Test"], check=True)
        subprocess.run(["git", "-C", str(self.repo), "config", "user.email", "test@example.com"], check=True)
        (self.repo / "factory").mkdir()
        (self.repo / "factory/sample.py").write_text("VALUE = 1\n")
        subprocess.run(["git", "-C", str(self.repo), "add", "."], check=True)
        subprocess.run(["git", "-C", str(self.repo), "commit", "-qm", "Initial"], check=True)

    def tearDown(self):
        self.temp.cleanup()

    def test_exports_small_patch_for_new_and_modified_files(self):
        (self.repo / "factory/sample.py").write_text("VALUE = 2\n")
        (self.repo / "tests").mkdir()
        (self.repo / "tests/test_add.py").write_text("assert True\n")
        dest = self.repo.parent / (self.repo.name + ".diff")
        try:
            self.assertGreater(export_patch(self.repo, dest), 0)
            patch = dest.read_text()
            self.assertIn("factory/sample.py", patch)
            self.assertIn("tests/test_add.py", patch)
        finally:
            dest.unlink(missing_ok=True)

    def test_rejects_workflow_changes(self):
        (self.repo / ".github/workflows").mkdir(parents=True)
        (self.repo / ".github/workflows/publish.yml").write_text("name: bad\n")
        with self.assertRaisesRegex(ValueError, "Disallowed"):
            validate_paths(self.repo)

    def test_rejects_guard_self_modification(self):
        (self.repo / "factory/agent_queue.py").write_text("def unsafe(): pass\n")
        with self.assertRaisesRegex(ValueError, "Guarded"):
            validate_paths(self.repo)

    def test_rejects_symlinks(self):
        (self.repo / "factory/link.py").symlink_to(self.repo / "factory/sample.py")
        with self.assertRaisesRegex(ValueError, "Symlinks"):
            validate_paths(self.repo)


if __name__ == "__main__":
    unittest.main()
