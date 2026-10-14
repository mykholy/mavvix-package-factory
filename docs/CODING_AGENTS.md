# Self-contained autonomous engineering agent (phase 3 pilot)

This pipeline operates only on **Mavvix Package Factory itself**. It does not create other package repositories or publish stable versions. GitHub Issues used as task inputs remain untrusted.

## Operating flow

1. Workflow runs daily, on dispatch, or when a repository Issue is opened.
2. Selector checks open Issues, only accepts titles starting with `[Agent Task]`, requires the exact GitHub owner as the issue author and clear Scope and Acceptance criteria. Already-processed branches are not repeated.
3. Read-only generator checks out the repository **without persisted credentials** and uses `openai/codex-action@v1` with the `:workspace` permission profile. It receives just the bounded task text and source tree. No GitHub write token is provided to Codex.
4. The generator validates the changed file allowlist and change-size budget. All repository changes outside `factory/`, `tests/`, `docs/`, `research/` are rejected. Guard code changes, symlinks and binaries are forbidden.
5. Generator compiles and tests the code, then uploads a Git patch artifact.
6. Separate privileged job applies only that patch on a clean checkout and creates a **Draft PR**, never a merge or release. It dispatches the quality workflow to test the generated branch.

## Initial prerequisite

For a personal GitHub repository, GitHub Actions needs an inference credential for Codex.

- Set repository secret `OPENAI_API_KEY` in **Settings → Secrets and variables → Actions**.
- Use an OpenAI **API Platform** key with a restricted project budget. ChatGPT Plus does not automatically grant API billing.
- Do not paste a secret into Issues, Actions logs, chat, README, or a committed config file.
- If the secret is absent, the workflow exits without running a paid model or opening an invented PR.

## Controls

- Maximum 1 task selected per run.
- Global workflow concurrency prevents duplicate simultaneous agent runs.
- Only Issues authored by GitHub user `mykholy` with exact title prefix and required scope are eligible.
- Maximum 16 changed files and 120 KB patch.
- Model process has no `GITHUB_TOKEN` and no Git checkout push credentials.
- Publisher has `contents:write`, `pull-requests:write`, `actions:write` only, and never executes changed source code.
- A GitHub token-created PR ordinarily does not trigger `pull_request` CI; the publisher explicitly dispatches `quality.yml` on the new branch.
- Independent review of architecture, integration, security and real-world adoption remains necessary before merging complex work.
- Publishing to Packagist/GitHub Releases remains disabled until a separate release plan is explicitly approved.

## Expanding to new PHP/Laravel package repos

This pilot deliberately does **not** create or write arbitrary package repositories. After an evidence-backed opportunity passes the established research gate, a separate controlled repository or installation token with limited `contents:write` and `pull-requests:write` would be required. Each generated package must run its own PHP/Laravel version matrix, static analysis and integration tests.

## Troubleshooting

- No eligible Issue: author must be `mykholy`, title `[Agent Task] ...`, with Scope and Acceptance criteria.
- No Codex run: check existence of `OPENAI_API_KEY` and model budget limits.
- Codex fails: read the workflow job, fix only code/config, no permission bypasses.
- Publisher fails creating PR: repository Actions settings may disallow `GITHUB_TOKEN` pull requests, or workflow permissions may need configuring.
- PR CI not triggered: confirm `workflow_dispatch` allowed on `quality.yml` and `actions:write` in the publish job.

Do not mark this capability fully activated until a real Issue generates a Draft PR and its CI succeeds.
