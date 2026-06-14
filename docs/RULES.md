# Project Rules

## Source Of Truth

- Canonical spec: `%USERPROFILE%\Downloads\padosoft-laravel-evidence-risk-review-SPEC-PLAN.md`. If that file is unavailable (e.g. on a different machine), continue from `docs/IMPLEMENTATION_PLAN.md` and the latest `docs/PROGRESS.md` entry.
- Durable local plan: `docs/IMPLEMENTATION_PLAN.md`.
- Agent instructions: `AGENTS.md`, `CLAUDE.md`, `skills/laravel-evidence-risk-review-plan/SKILL.md`, `.claude/skills/copilot-pr-review-loop/SKILL.md`, `.claude/skills/codex-pr-review-fallback/SKILL.md`, `.github/copilot-instructions.md`, and `.github/PULL_REQUEST_TEMPLATE.md`.
- Lessons learned: `docs/LESSON.md`.
- Current work state: `docs/PROGRESS.md`.

## Implementation Defaults

- Package name: `padosoft/laravel-evidence-risk-review`.
- Namespace: `Padosoft\EvidenceRiskReview`.
- License: Apache-2.0, matching the repository `LICENSE` file.
- PHP: `^8.3`; validate locally with Herd PHP 8.5 where possible.
- Laravel: `^13.0`.
- Testbench for Laravel package feature tests.
- SQLite in-memory for feature tests.
- No hard dependency on AskMyDocs, LLM SDKs, MCP server packages, or host auth packages.
- Optional adapters must be guarded with `class_exists()` or package discovery checks.

## Architecture Rules

- `ReviewEngine` is the single orchestration point.
- Facade, Artisan commands, HTTP controllers, and MCP tools must not contain business logic.
- Profiles are config/registry data. Domain-specific medical/legal/finance checklists do not belong in core service logic.
- Evidence tiers are extensible: built-in enum plus config-driven `EvidenceTierValue` for custom tiers.
- Heavy/LLM checks are budget-gated and recorded when skipped.
- Review log is append-only. Do not add update paths.
- Persistence failures, unknown profiles, invalid budgets, and LLM transport failures must surface loudly and be represented in findings/logs where appropriate.
- API, MCP, and LLM features are disabled by default and must have tests for OFF and ON paths.

## Standalone-Agnostic Guardrails

The architecture test must reject these needles in `src/` and package dependencies:

- `AskMyDocs`
- `lopadova/askmydocs`
- `padosoft/askmydocs`
- `KnowledgeDocument`
- `KbSearchService`
- `knowledge_documents`
- `knowledge_chunks`
- `kb_nodes`
- `kb_edges`
- `kb_canonical_audit`

If another host-specific term is introduced during work, add it to this list and to the architecture test.

## Security Rules

- Never expose API keys, authorization headers, provider tokens, raw LLM payload secrets, or partial secret previews.
- HTTP auth and tenant authorization are the host application's responsibility; package endpoints expose middleware hooks only.
- Treat external URLs and profile/tier hint patterns as untrusted input. Escape literal domain hints before regex matching.
- JSON and command output should be sanitized and deterministic.
- No network calls in default CI tests. Live tests are opt-in and skip without env.

## Testing Rules

Every completed task must define guardrails and run the relevant subset:

```text
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/phpunit
npm run build
npm run test
npm run e2e
```

- Pure backend/package tasks require PHPUnit at minimum and architecture tests when dependencies or namespace boundaries are touched.
- UI/UX tasks require Vitest/Vite when frontend code exists and Playwright scenarios for every meaningful interaction.
- Live/provider tests must be opt-in and skipped by default when credentials are missing.
- When adding a new test family, add it to `phpunit.xml`; do not accept a green PHPUnit run that skipped the new directory.
- Testbench config that affects provider boot, route registration, or package discovery must be set before boot, for example with `#[WithConfig(..., defer: false)]`.
- OpenAPI changes require Redocly lint. Explicit no-auth APIs should declare `security: []`, include `info.license`, and define at least one 4XX response per operation.
- GitHub Actions workflow changes require a local YAML sanity check such as `npx --yes yaml-lint .github/workflows/ci.yml` when no stronger local action linter is available.
- If Composer CLI times out even for `composer --version`, record it as a local tool blocker, verify `composer.json` parsing if relevant, and retry later.
- If a tool is unavailable, record the exact blocker in `docs/PROGRESS.md`.

## Review Rules

- Temporary override from 2026-06-14: skip per-W/per-subtask local Copilot, GitHub Copilot, and Codex review loops for W3-W8. Keep local gates, PRs, merges, and CI checks. Run one deep AI review over the completed roadmap before final hardening/release. If an AI review was already in flight before this override, fix valid findings already received but do not request another pass.
- Run local Copilot review against the full branch diff versus `origin/main` before pushing a task branch.
- Local Copilot review is report-only by default: use stdin without `--autopilot`, do not use `--yolo`; the prompt must explicitly say not to edit files, not to run shell commands, not to stage files, not to commit, to focus on correctness, tests, security, Laravel package conventions, standalone-agnostic boundaries, default-OFF behavior, and missing edge cases, and to return either `no findings` or a concise numbered list of actionable findings.
- After every local Copilot review, run `git status` and inspect any diff before staging. Copilot CLI can still attempt filesystem edits despite report-only instructions; keep only intentional changes.
- If local Copilot review fails three consecutive times for the same subtask because of quota, timeout, or CLI failure, record all three attempts in `docs/PROGRESS.md`, proceed with the PR loop, and retry local Copilot review on the next macro/subtask. This does not bypass tests, remote GitHub Copilot Code Review, or CI.
- Use GitHub Copilot Code Review on every PR.
- Request GitHub Copilot Code Review with `gh pr create --reviewer '@copilot'` whenever possible. For existing PRs try `gh pr edit <PR> --add-reviewer '@copilot'`, then `gh pr edit <PR> --add-reviewer copilot-pull-request-reviewer` if Copilot cannot be resolved or the command no-ops after verification. Quote `@copilot` in PowerShell. Verify it really started through `requested_reviewers`, `reviewRequests`, reviews, or comments. If no visible request/review/comment appears after polling, Copilot did not start and the next action is repository/organization Copilot settings, automatic review rulesets, or manual GitHub UI request.
- After each fix push, request or re-request Copilot review again unless an automatic review-on-push ruleset is visibly producing a fresh Copilot review. Poll at least 60 seconds before judging CI/review start, and up to 15 minutes for Copilot unless GitHub reports a clear blocker.
- Copilot has responded only when review summaries, top-level comments, inline comments, or review threads show a Copilot author. Prefer comments/reviews tied to the current `headRefOid`; old-commit review comments are not enough for a changed diff.
- Read all Copilot feedback surfaces before merging: `gh pr view --json ...`, `pulls/<PR>/reviews`, `issues/<PR>/comments`, `pulls/<PR>/comments`, and GraphQL `reviewThreads` for `isResolved`/`isOutdated`.
- If Copilot is blocked by quota, budget, access, or prolonged non-response after documented attempts, automatically switch to ChatGPT Codex Connector by commenting `@codex review`. Verify `chatgpt-codex-connector[bot]` responds in reviews/comments/reactions, preferably for the current `headRefOid`; fix actionable findings and repeat. Copilot remains the primary source when available.
- Do not use Codex instead of Copilot when Copilot is available; Codex is allowed only for the documented quota/budget/access/non-response fallback or an explicit user request.
- Merge only after local gates, remote CI, and Copilot comments are resolved.
- Before W7, the remote CI gate means no required checks are failing or pending. W7 introduces the GitHub Actions workflow; after that, every PR must wait for configured CI to pass.

## Documentation Rules

- Update `docs/PROGRESS.md` after meaningful implementation, verification, review, PR, merge, or blocker events, but keep it as durable handoff state rather than a per-poll remote log.
- Update `docs/LESSON.md` after non-obvious setup facts, review feedback, or durable design decisions.
- Keep dated entries in `YYYY-MM-DD` format.
- Final package README must be community-grade: badges, optional banner/screenshots if resources exist, TOC, innovation summary, quick start, architecture, configuration, testing, security, contributing, changelog, and license.

## Release Rules

- Tag milestone releases as `v0.x.0` after each macro gate when appropriate.
- Tag final package as `v1.0.0`.
- Publish a GitHub Release after final validation.
- Packagist submission happens only after v1.0.0 is feature-complete and documented.
