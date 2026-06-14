# Laravel Evidence Risk Review Implementation Plan

This is the durable repo-local execution plan for `padosoft/laravel-evidence-risk-review`.

Canonical full spec:

```text
%USERPROFILE%\Downloads\padosoft-laravel-evidence-risk-review-SPEC-PLAN.md
```

## Goal

Build a standalone Laravel 13 package that reviews grounded AI answers for evidence strength, boundary conditions, and risk, without depending on AskMyDocs or any specific host application.

The package exposes one core through three thin surfaces:

- PHP API: facade, DTOs, services, and Artisan commands.
- HTTP API: optional default-OFF REST endpoints.
- MCP: optional default-OFF tool definitions and adapter.

## Package Invariants

- Namespace: `Padosoft\EvidenceRiskReview`.
- Composer package: `padosoft/laravel-evidence-risk-review`.
- PHP: `^8.3`, validated on PHP 8.3/8.4/8.5.
- Laravel: `^13.0`.
- No hard LLM SDK dependency.
- No AskMyDocs dependency or symbol leakage.
- HTTP, MCP, and LLM features default to OFF.
- Controllers, commands, and tools contain no business logic.
- Every heavy/LLM path is budget-gated and auditable.
- Review logs are append-only.

## Branch Strategy

User rule: one macro branch per macro task; each subtask gets a PR into the macro branch. When the macro is complete, open the macro PR into `main`.

Temporary review strategy override from 2026-06-14: while completing W3-W8, do not launch local Copilot, GitHub Copilot, or Codex reviews for every W/subtask. Keep local gates, PRs, merges, and CI checks. Run one deep AI review over the completed roadmap before final hardening/release. If an AI review was already running before this override, fix valid findings already received but do not request another pass.

Macro branches:

| Macro | Branch | Scope |
| --- | --- | --- |
| W1 | `macro/w1-foundation` | Composer/package scaffold, ServiceProvider, enums, DTOs, tier resolver/labeler cheap path, architecture test. |
| W2 | `macro/w2-sweep-core` | Risk checks, sweep engine, verdict reduction, profile contract/registry/config profiles, budget meter. |
| W3 | `macro/w3-llm-engine-log` | LLM contract/null/callback implementations, heavy checks, `ReviewEngine`, log stores, migration. |
| W4 | `macro/w4-php-surface` | Facade, `reviewArray()`, Artisan commands, command exit codes. |
| W5 | `macro/w5-http-surface` | Form requests, controllers, routes, default-OFF API, OpenAPI 3.1, error contract. |
| W6 | `macro/w6-mcp-surface` | MCP tool definitions, registry, optional Laravel MCP adapter. |
| W7 | `macro/w7-dx-docs-ci` | README, vibe-coding pack, env example, live tests, contributing docs, CI matrix. |
| W8 | `macro/w8-hardening-release` | PHPStan/Pint hardening, edge cases, final README polish, Packagist, `v1.0.0` release. |

Current bootstrap branch:

```text
task/bootstrap-agent-rules
```

This branch exists only to add durable process docs before application code.

CI note: no GitHub Actions workflow exists until W7. For Bootstrap and W1-W6, the remote CI gate means no required checks are failing or pending. W7 introduces the actual workflow; all subsequent work must pass it.

## Subtask Exit Gate

Each subtask must have:

- A precise objective.
- Implementation details scoped to the subtask.
- Guardrails and tests listed before closure.
- Green local gates for the relevant stack.
- Local Copilot review against the full branch diff versus `origin/main`, with zero actionable comments.
- `git status` checked after local Copilot review and any Copilot-created diff inspected before staging.
- PR into the current macro branch.
- GitHub Copilot Code Review requested and confirmed, or Codex connector fallback used after documented Copilot quota/budget/access/non-response blocker.
- Copilot feedback read from review summaries, top-level comments, inline comments, and review threads.
- Remote CI green.
- Copilot comments resolved.
- `docs/PROGRESS.md` updated.
- `docs/LESSON.md` updated when anything useful was learned.

## Local Gates

Use the relevant subset:

```text
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/phpunit
npm run build
npm run test
npm run e2e
```

For this package:

- W1-W4 are backend/package-heavy and require PHPUnit plus architecture tests.
- W5 adds HTTP feature tests and OpenAPI linting.
- W6 adds MCP schema/handler tests.
- W7/W8 add docs, CI, live-suite skip behavior, static analysis, and final release checks.
- Playwright is required only if a UI/UX surface is introduced. HTTP/MCP/PHP-only tasks do not need Playwright.

## Local Copilot Review Loop

Before pushing:

```powershell
git diff --no-ext-diff origin/main...HEAD > "$env:TEMP\laravel-evidence-risk-review.diff"
Get-Content -Raw "$env:TEMP\laravel-evidence-risk-review.diff" | copilot -p "/review The full diff is provided on stdin. REPORT ONLY. Do not edit files. Do not run shell commands. Do not stage files. Do not commit. Focus on correctness, tests, security, Laravel package conventions, standalone-agnostic boundaries, default-OFF behavior, and missing edge cases. Return either 'no findings' or a concise numbered list of actionable findings."
```

Use stdin without `--autopilot` by default. On this repo, file-path `--autopilot` review repeatedly timed out and attempted edits despite report-only prompts.

If the command does not return within 5 minutes, stop it and record the blocker in `docs/PROGRESS.md` with the exact timeout duration. If local Copilot review fails three consecutive times for the same subtask because of quota, timeout, or CLI failure, record all three attempts, proceed with the PR loop, and retry local Copilot review on the next macro/subtask. This exception applies only to local Copilot review; it does not bypass local tests, remote GitHub Copilot Code Review, or CI.

If Copilot cannot read the file, inline the diff when small enough. Fix actionable comments, rerun local gates, and repeat.

After every local Copilot review, run `git status` and inspect any diff before staging. Copilot CLI can still attempt filesystem edits despite report-only instructions; keep only intentional changes.

## Remote PR Loop

1. Push the subtask branch.
2. Open PR into the macro branch and request Copilot in the same command whenever possible:

   ```text
   gh pr create --base <base-branch> --head <head-branch> --title "<title>" --body-file <body-file> --reviewer '@copilot'
   ```

3. If the PR already exists, request Copilot reviewer:

   ```text
   gh pr edit <PR> --add-reviewer '@copilot'
   ```

   Quote `@copilot` in PowerShell. If GitHub cannot resolve Copilot or the command no-ops after verification, retry with:

   ```text
   gh pr edit <PR> --add-reviewer copilot-pull-request-reviewer
   ```

4. Verify Copilot actually started:

   ```text
   gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/requested_reviewers
   gh pr view <PR> --json reviewRequests,reviews,comments,reviewDecision,statusCheckRollup
   ```

   The verification must show Copilot as a pending reviewer, a review, or comments. If CLI/API/GraphQL returns success but `requested_reviewers`, `reviewRequests`, reviews, and comments remain empty after polling, Copilot did not start; check repository/organization Copilot Code Review settings, enable automatic review rulesets where appropriate, or request from the GitHub UI.

5. If blocked by `read:project`, use the GraphQL fallback documented in `AGENTS.md`.
6. Poll CI and Copilot comments. Wait at least 60 seconds before deciding CI/review did not start; poll Copilot up to 15 minutes unless GitHub reports a clear blocker.
7. Read all Copilot feedback surfaces: PR JSON, review summaries, issue comments, inline comments, and GraphQL reviewThreads.
8. Fix, test, push, request/re-request review, and recheck until green. Prefer Copilot reviews/comments tied to the current `headRefOid`.
9. If Copilot remains blocked by quota, budget, access, or prolonged non-response, comment `@codex review`, verify `chatgpt-codex-connector[bot]` responded for the current commit where possible, fix actionable findings, push, and repeat the Codex fallback loop.
10. Merge subtask PR into macro branch.
11. When all subtasks in the macro are complete, open macro PR into `main` and repeat the same gate.

## Milestones

### Bootstrap - Agent Rules And Process Docs

Branch: `task/bootstrap-agent-rules` → PR into `main`.

> **Exception:** This is the only branch that targets `main` directly. All subsequent W1–W8 work follows the subtask-branch → macro-branch → `main` pattern described above.

Deliver:

- `AGENTS.md`
- `CLAUDE.md`
- `docs/RULES.md`
- `docs/LESSON.md`
- `docs/PROGRESS.md`
- `docs/IMPLEMENTATION_PLAN.md`
- `skills/laravel-evidence-risk-review-plan/SKILL.md`
- `skills/laravel-evidence-risk-review-plan/references/plan-location.md`
- `.claude/skills/laravel-evidence-risk-review-plan/SKILL.md`
- `.claude/skills/copilot-pr-review-loop/SKILL.md`
- `.claude/skills/codex-pr-review-fallback/SKILL.md`
- `.claude/rules/laravel-evidence-risk-review.md`
- `.github/copilot-instructions.md`
- `.github/PULL_REQUEST_TEMPLATE.md`
- `.gitignore`

Exit gate:

- `git diff --check` clean.
- Local Copilot review against full branch diff returns zero actionable comments.
- PR opened into `main`.
- GitHub Copilot Code Review requested and confirmed.
- Codex connector fallback completed if Copilot stays unavailable due to quota/budget/access/non-response.
- Copilot feedback surfaces checked, or blocked remote action recorded.
- Remote CI green.
- `docs/PROGRESS.md` updated after merge.
- `docs/LESSON.md` updated with any durable discoveries.
- `AGENTS.md` Current Priority is pre-updated in this Bootstrap PR to point at W1 as the next action after merge; do not make a direct post-merge commit to `main` for this.
- Merge into `main`.

### W1 - Foundation

Deliver:

- `composer.json`
- Service provider
- Package config shell
- Enums:
  - `EvidenceTier`
  - `RiskVerdict`
  - `RiskCheckKind`
  - `RiskCostClass`
  - `ClaimAssertiveness`
- Immutable DTOs with `fromArray()` / `toArray()`
- `EvidenceTierValue`
- `TierResolver`
- cheap-only `EvidenceTierLabeler`
- architecture test rejecting host coupling

Exit gate:

- Composer validation green.
- Unit tests green.
- Architecture test green.
- Plus all Subtask Exit Gate requirements above.
- Tag candidate `v0.1.0` after macro merge if appropriate.

### W2 - Sweep Core

Deliver:

- `RiskCheck` contract.
- Built-in cheap checks.
- `RiskSweepEngine`.
- Verdict precedence reduction.
- `RiskProfileContract`.
- `ConfigRiskProfile`.
- `DomainProfileRegistry`.
- Five built-in profile config files: default, engineering, medical, legal, finance.
- `ReviewBudget`, `BudgetMeter`, `BudgetConsumption`.

Exit gate:

- Sweep/check/profile/budget tests green.
- Supports-predicate mutex tests green.
- Plus all Subtask Exit Gate requirements above.
- Tag candidate `v0.2.0`.

### W3 - LLM Boundary, Engine, Log

Deliver:

- `EvidenceReviewerLlmContract`.
- `LlmRequest`, `LlmResponse`, LLM exceptions.
- `NullEvidenceReviewerLlm`.
- `CallbackEvidenceReviewerLlm`.
- Heavy checks and LLM tier refinement.
- `ReviewEngine`.
- `ReviewLogStore` contract.
- `DatabaseReviewLogStore`, `ArrayReviewLogStore`, `NullReviewLogStore`.
- Published append-only migration.
- R26 short-circuit tests proving no LLM call when cheap pass clears.

Exit gate:

- Engine/log/cost guard tests green.
- SQLite feature tests green.
- Plus all Subtask Exit Gate requirements above.
- Tag candidate `v0.3.0`.

### W4 - PHP Surface

Deliver:

- Facade `EvidenceRiskReview`.
- `review()`, `reviewArray()`, `labelTier()`, `listProfiles()`, `taxonomy()`.
- Artisan commands:
  - `evidence:review`
  - `evidence:profiles`
  - `evidence:taxonomy`
  - `evidence:log`
- Command exit codes: success, flagged, failure.

Exit gate:

- Command feature tests green.
- Public API docs/examples updated.
- Plus all Subtask Exit Gate requirements above.
- Tag candidate `v0.4.0`.

### W5 - HTTP Surface

Deliver:

- Configurable routes under `evidence-risk-review/api` by default.
- API default-OFF path tests.
- Form requests and controllers.
- REST endpoints:
  - `POST /reviews`
  - `GET /reviews/{review}`
  - `GET /profiles`
  - `GET /profiles/{key}`
  - `GET /taxonomy`
  - `GET /openapi.yaml`
- Error contract for unknown profile, unknown review, unavailable LLM, validation errors.
- OpenAPI 3.1 spec.

Exit gate:

- HTTP feature tests green.
- OpenAPI lint green.
- Plus all Subtask Exit Gate requirements above.
- Tag candidate `v0.5.0`.

### W6 - MCP Surface

Deliver:

- Framework-agnostic `McpToolDefinition`.
- Tools:
  - `evidence_review.assess`
  - `evidence_review.label_tier`
  - `evidence_review.list_profiles`
- `McpToolRegistry`.
- Optional `LaravelMcpToolAdapter` behind `class_exists()` guard.

Exit gate:

- MCP schema tests green.
- MCP handler tests green.
- Optional adapter does not force dependency.
- Plus all Subtask Exit Gate requirements above.
- Tag candidate `v0.6.0`.

### W7 - DX, Docs, CI

Deliver:

- WOW README inspired by `AskMyDocs`, adapted to this package.
- Badges, optional banner/screenshots if resources exist.
- TOC, quick start, architecture, configuration, surfaces, profiles, testing, security, contributing, license.
- `.env.example`.
- Opt-in Live test suite that skips without env.
- `CONTRIBUTING.md`.
- `CODE_OF_CONDUCT.md`.
- `SECURITY.md`.
- `CODEOWNERS`.
- Claude/agent/vibe-coding pack included in repo.
- GitHub Actions CI matrix: PHP 8.3/8.4/8.5 and Laravel 13.

Exit gate:

- CI workflow locally sanity-checked where possible.
- Live suite skip behavior tested.
- Plus all Subtask Exit Gate requirements above.
- Tag candidate `v0.7.0`.

### W8 - Hardening And Release

Deliver:

- PHPStan level 8 clean with zero baseline unless explicitly approved.
- Pint clean.
- Edge-case coverage:
  - empty claims
  - all unverified sources
  - budget exhausted
  - unknown custom tier
  - unknown profile
  - append failure
- Final README polish.
- Review `docs/LESSON.md` and fold durable know-how into `AGENTS.md`, `CLAUDE.md`, `docs/RULES.md`, and local skills.
- Tag `v1.0.0`.
- GitHub Release.
- Packagist submission/readiness.

Exit gate:

- Full local gates green.
- Remote CI green.
- Copilot review resolved.
- Plus all Subtask Exit Gate requirements above.
- Release notes published.
