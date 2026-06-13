---
name: laravel-evidence-risk-review-plan
description: Continue or resume the padosoft/laravel-evidence-risk-review package implementation. Use when working in this repo, when context was compacted or lost, or when enforcing the branch, PR, Copilot review, documentation, testing, Laravel package, standalone-agnostic, default-OFF, and release rules.
---

# Laravel Evidence Risk Review Plan

This is the full repo-local skill. Keep the Claude/Copilot summary at `.claude/skills/laravel-evidence-risk-review-plan/SKILL.md` in sync when changing process rules.

## Start Here

Read these files before editing application code:

1. `AGENTS.md`
2. `CLAUDE.md`
3. `docs/IMPLEMENTATION_PLAN.md`
4. `docs/RULES.md`
5. `docs/PROGRESS.md`
6. `docs/LESSON.md`

The canonical full specification is saved at:

```text
%USERPROFILE%\Downloads\padosoft-laravel-evidence-risk-review-SPEC-PLAN.md
```

If that file is unavailable, continue from `docs/IMPLEMENTATION_PLAN.md` and the latest `docs/PROGRESS.md` entry.

## Procedure

1. Run `git status --short --branch` before changing files.
2. Re-read `docs/LESSON.md` and pass its relevant contents to any background worker.
3. Work only on the current macro/subtask branch.
4. Define objective, implementation details, and guardrails for the slice.
5. Keep public adapters thin and route all business behavior through the core services.
6. Preserve standalone-agnostic boundaries.
7. Add or update tests with the change.
8. Run relevant local gates.
9. Run local Copilot review on the full diff versus `origin/main` in report-only mode: use stdin without `--autopilot`, do not use `--yolo`; the prompt must say not to edit files, not to run shell commands, not to stage files, not to commit, focus on correctness, tests, security, Laravel package conventions, standalone-agnostic boundaries, default-OFF behavior, and missing edge cases, and return either `no findings` or a concise numbered list of actionable findings. If local Copilot review fails three consecutive times for the same subtask because of quota, timeout, or CLI failure, record all three attempts, proceed with the PR loop, and retry local Copilot review on the next macro/subtask. This does not bypass tests, remote GitHub Copilot Code Review, or CI.
10. After every local Copilot review, run `git status` and inspect any diff before staging. Copilot CLI can still attempt filesystem edits despite report-only instructions; keep only intentional changes.
11. Update `docs/PROGRESS.md`.
12. Update `docs/LESSON.md` when learning something durable.
13. Use `.claude/skills/copilot-pr-review-loop/SKILL.md` for PR review: request/re-request Copilot after each push, verify it started, read review summaries, issue comments, inline comments, and GraphQL reviewThreads, and confirm response on the current `headRefOid` where possible.
14. If remote PR/Copilot/CI steps cannot run, record the blocker and next action.

CI note: before W7, the remote CI gate means no required checks are failing or pending. W7 introduces the GitHub Actions workflow; after that, every PR must wait for configured CI to pass.

## Guardrails

- No AskMyDocs references or dependencies.
- No default network calls in tests.
- API/MCP/LLM default-OFF paths tested.
- Heavy checks respect `BudgetMeter`.
- Review log append-only.
- Unknown/invalid states fail loudly.
- UI tasks, if any, require Playwright interaction coverage.

## Copilot Review Fallback

Use `.claude/skills/copilot-pr-review-loop/SKILL.md` for the GitHub Copilot PR loop. Prefer `gh pr create --reviewer '@copilot'`; for an existing PR try `gh pr edit <PR> --add-reviewer '@copilot'`, then `gh pr edit <PR> --add-reviewer copilot-pull-request-reviewer` if Copilot cannot be resolved or the command no-ops. Quote `@copilot` in PowerShell. Verify with `requested_reviewers` and PR review/comment JSON. Use the GraphQL/API fallbacks in `AGENTS.md` only as attempts; command success is not enough unless Copilot appears in verification output.

After a fix push, request/re-request Copilot unless an automatic review-on-push ruleset visibly produced a fresh review. Copilot has answered only when review summaries, issue comments, inline comments, or reviewThreads include a Copilot author; prefer comments/reviews tied to the current `headRefOid`.
