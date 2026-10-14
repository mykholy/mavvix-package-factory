# Guarded coding agent integration

This repository will support optional assignment of narrowly scoped maintainer-authored maintenance issues to GitHub Copilot cloud agent. Assignment requires an eligible paid GitHub Copilot plan and a user-owned GitHub token configured in repository Actions secrets. The default GitHub Actions token cannot assign Copilot.

Official reference: https://docs.github.com/en/copilot/how-tos/use-copilot-agents/cloud-agent/use-cloud-agent-via-the-api

Research digests and externally-authored issues are never dispatched. The agent must produce a draft PR with tests; no automatic merges, tags, releases or Packagist submissions.

The integration is disabled until its required credential is explicitly configured. The workflow must report whether an assignment was actually accepted by GitHub.
