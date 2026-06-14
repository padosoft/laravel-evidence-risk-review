---
name: laravel-evidence-risk-review-plan
description: Resume and enforce the implementation process for the padosoft/laravel-evidence-risk-review Laravel package.
---

# Laravel Evidence Risk Review Plan

This is the summary version for Copilot CLI. The full procedure is in `skills/laravel-evidence-risk-review-plan/SKILL.md`; keep both in sync when updating process steps.

Read, in order:

1. `AGENTS.md`
2. `CLAUDE.md`
3. `docs/IMPLEMENTATION_PLAN.md`
4. `docs/RULES.md`
5. `docs/PROGRESS.md`
6. `docs/LESSON.md`

Then follow the branch, test, local Copilot, PR, GitHub Copilot Code Review, CI, merge, progress, and lesson rules documented there.

Core invariants:

- Standalone Laravel package.
- Laravel 13 and PHP `^8.3`.
- PHP 8.3/8.4/8.5 CI matrix.
- Before W7, the remote CI gate means no required checks are failing or pending. W7 introduces the GitHub Actions workflow; after that, every PR must wait for configured CI to pass.
- No AskMyDocs dependency or symbol leak.
- HTTP, MCP, and LLM default-OFF.
- One `ReviewEngine`; thin PHP/HTTP/MCP/Artisan adapters.
- Every task has tests; UI tasks require Playwright.
- Local Copilot review is report-only: use stdin without `--autopilot`, no `--yolo`, and the prompt must say not to edit files, not to run shell commands, not to stage files, not to commit, to focus on correctness, tests, security, Laravel package conventions, standalone-agnostic boundaries, default-OFF behavior, and missing edge cases, and to return either `no findings` or a concise numbered list of actionable findings.
- After every local Copilot review, run `git status` and inspect any diff before staging. Copilot CLI can still attempt filesystem edits despite report-only instructions; keep only intentional changes.
- If local Copilot review fails three consecutive times for the same subtask because of quota, timeout, or CLI failure, record all three attempts, proceed with the PR loop, and retry local Copilot review on the next macro/subtask. This does not bypass tests, remote GitHub Copilot Code Review, or CI.
- Use `.claude/skills/copilot-pr-review-loop/SKILL.md` for GitHub Copilot PR review: prefer `gh pr create --reviewer '@copilot'`; for existing PRs try quoted `@copilot`, then `copilot-pull-request-reviewer`; verify Copilot is visible as pending reviewer, review, or comments.
- After every fix push, request/re-request Copilot unless automatic review-on-push visibly produced a fresh review. Read review summaries, issue comments, inline comments, and reviewThreads; Copilot has responded only when a Copilot-authored review/comment is visible, preferably on the current `headRefOid`.
- If Copilot is blocked by quota, budget, access, or prolonged non-response, use `.claude/skills/codex-pr-review-fallback/SKILL.md`: comment `@codex review`, verify `chatgpt-codex-connector[bot]` responds, fix actionable findings, and repeat.
