# Claude Context

This file mirrors the durable rules for Claude Code and other Claude-compatible agents.

## Start Here

1. Read `AGENTS.md`.
2. Read `docs/IMPLEMENTATION_PLAN.md`.
3. Read `docs/RULES.md`.
4. Read `docs/PROGRESS.md`.
5. Read `docs/LESSON.md`.
6. Read `.claude/skills/copilot-pr-review-loop/SKILL.md`.
7. Read `.claude/skills/codex-pr-review-fallback/SKILL.md`.
8. Read the canonical spec at `%USERPROFILE%\Downloads\padosoft-laravel-evidence-risk-review-SPEC-PLAN.md` when deeper implementation detail is needed. If that file is unavailable, continue from `docs/IMPLEMENTATION_PLAN.md` and the latest `docs/PROGRESS.md` entry.

## Non-Negotiables

- Temporary review strategy override from 2026-06-14: do not launch local Copilot, GitHub Copilot, or Codex reviews for each W/subtask while completing W3-W8. Run local gates and PR/CI flow, then perform one deep AI review before final hardening/release. Fix valid findings already received from an in-flight review, but do not request another pass.
- This package is standalone. Do not import or reference AskMyDocs, `KnowledgeDocument`, `KbSearchService`, AskMyDocs tables, or sibling package symbols.
- Laravel 13.x, PHP `^8.3`, CI across PHP 8.3/8.4/8.5, local Herd PHP 8.5 preferred when available.
- HTTP API, MCP, and LLM are default-OFF.
- No LLM SDK dependency in the package. Hosts bind `EvidenceReviewerLlmContract`.
- Controllers, commands, facades, and MCP adapters are thin wrappers over `ReviewEngine`.
- Every completed task needs precise tests. UI/UX tasks also need Playwright interaction scenarios.
- Keep `docs/PROGRESS.md` and `docs/LESSON.md` current.
- Follow the branch/PR/Copilot/CI loop in `AGENTS.md`.
- Keep PHPUnit suite coverage honest: add new test directories to `phpunit.xml`, use `#[WithConfig(..., defer: false)]` for Testbench provider-boot config, and verify live suites skip without env.
- Redocly OpenAPI lint and workflow YAML lint are part of DX/API guardrails once those files exist.
- If Composer CLI itself times out, record the exact command failures and retry later; do not confuse a tool hang with invalid package metadata.

## Review Checklist

- PHPUnit unit/feature/architecture tests cover the changed behavior.
- Pint and PHPStan level 8 stay clean once configured.
- Vite/Vitest/Playwright run when browser UI exists.
- Local Copilot review is report-only (use stdin without `--autopilot`, no `--yolo`; prompt must say not to edit files, not to run shell commands, not to stage files, not to commit, to focus on correctness, tests, security, Laravel package conventions, standalone-agnostic boundaries, default-OFF behavior, and missing edge cases, and to return either `no findings` or a concise numbered list of actionable findings) and returns zero actionable comments before push.
- After local Copilot review, inspect `git status` and any diff before staging. Copilot CLI can still attempt filesystem edits despite report-only instructions; keep only intentional changes.
- If local Copilot review fails three consecutive times for the same subtask because of quota, timeout, or CLI failure, record all three attempts in `docs/PROGRESS.md`, proceed with the PR loop, and retry local Copilot review on the next macro/subtask. This does not bypass tests, remote GitHub Copilot Code Review, or CI.
- GitHub Copilot Code Review and CI are resolved before merge.
- Request GitHub Copilot Code Review with `--reviewer '@copilot'` at PR creation whenever possible. For existing PRs try `gh pr edit <PR> --add-reviewer '@copilot'`, then `gh pr edit <PR> --add-reviewer copilot-pull-request-reviewer` if Copilot cannot be resolved or the command no-ops. Quote `@copilot` in PowerShell. Verify it really started through requested reviewers, review requests, reviews, or comments; command success alone is not enough.
- After every fix push, request/re-request Copilot review unless an automatic review-on-push ruleset visibly produced a fresh review. Read review summaries, issue comments, inline comments, and GraphQL reviewThreads; Copilot has responded only when a Copilot-authored review/comment is visible, preferably for the current `headRefOid`.
- If Copilot is blocked by quota, budget, access, or prolonged non-response, use the Codex connector fallback: comment `@codex review`, verify `chatgpt-codex-connector[bot]` responded on the current commit, fix actionable findings, and repeat.
- Before W7, the remote CI gate means no required checks are failing or pending. W7 introduces the GitHub Actions workflow; after that, every PR must wait for configured CI to pass.
