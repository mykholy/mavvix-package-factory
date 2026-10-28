"""Regression checks for the generated-code trust boundary in CI."""

import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


WORKFLOW = Path(__file__).resolve().parents[1] / ".github/workflows/codex-engineering.yml"


class PublishTrustBoundaryTests(unittest.TestCase):
    def test_publish_uses_trusted_isolated_validator_before_and_after_patch(self):
        workflow = WORKFLOW.read_text(encoding="utf-8")
        publish = workflow.split("\n  publish-draft:\n", 1)[1]

        preserve = 'cp factory/agent_queue.py "$RUNNER_TEMP/agent_queue_guard.py"'
        apply_patch = 'git apply "$RUNNER_TEMP/validated/agent.patch"'
        validate = 'python -I "$RUNNER_TEMP/agent_queue_guard.py" validate'

        self.assertIn(preserve, publish)
        self.assertIn(apply_patch, publish)
        self.assertIn(validate, publish)
        self.assertLess(publish.index(preserve), publish.index(apply_patch))
        self.assertLess(publish.index(apply_patch), publish.index(validate))
        self.assertNotIn("python -m factory.agent_queue validate", publish)

    def test_preserved_guard_rejects_modified_guard_without_running_it(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            repo = root / "checkout"
            repo.mkdir()
            (repo / "factory").mkdir()
            guard = root / "trusted_guard.py"
            guard.write_text(
                (WORKFLOW.parents[2] / "factory/agent_queue.py").read_text(encoding="utf-8"),
                encoding="utf-8",
            )
            subprocess.run(["git", "init", "-q", str(repo)], check=True)
            subprocess.run(["git", "-C", str(repo), "config", "user.name", "Test"], check=True)
            subprocess.run(["git", "-C", str(repo), "config", "user.email", "test@example.com"], check=True)
            protected = repo / "factory/agent_queue.py"
            protected.write_text("# trusted baseline\n", encoding="utf-8")
            subprocess.run(["git", "-C", str(repo), "add", "."], check=True)
            subprocess.run(["git", "-C", str(repo), "commit", "-qm", "baseline"], check=True)

            marker = root / "untrusted-executed"
            protected.write_text(
                "from pathlib import Path\nPath(" + repr(str(marker)) + ").write_text('bad')\n",
                encoding="utf-8",
            )
            result = subprocess.run(
                [
                    sys.executable, "-I", str(guard), "validate",
                    "--repo", str(repo), "--patch-output", str(root / "patch"),
                ],
                cwd=repo, text=True, capture_output=True, check=False,
            )
            self.assertNotEqual(result.returncode, 0)
            self.assertIn("Guarded", result.stderr)
            self.assertFalse(marker.exists())

    def test_generation_validator_is_preserved_before_codex_edits(self):
        workflow = WORKFLOW.read_text(encoding="utf-8")
        generate = workflow.split("\n  generate:\n", 1)[1].split("\n  publish-draft:\n", 1)[0]
        preserve = 'cp factory/agent_queue.py "$RUNNER_TEMP/agent_queue_guard.py"'
        codex = "uses: openai/codex-action@v1"

        self.assertIn(preserve, generate)
        self.assertIn(codex, generate)
        self.assertLess(generate.index(preserve), generate.index(codex))


if __name__ == "__main__":
    unittest.main()
