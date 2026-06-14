# Laravel Evidence Risk Review Agent Guide

This repository is the standalone Laravel package:

```text
padosoft/laravel-evidence-risk-review
```

Canonical implementation spec:

```text
%USERPROFILE%\Downloads\padosoft-laravel-evidence-risk-review-SPEC-PLAN.md
```

If that file is unavailable (e.g. on a different machine), continue from `docs/IMPLEMENTATION_PLAN.md` and the latest `docs/PROGRESS.md` entry.

If context is missing, read that spec first, then read:

- `docs/IMPLEMENTATION_PLAN.md`
- `docs/RULES.md`
- `docs/PROGRESS.md`
- `docs/LESSON.md`
- `skills/laravel-evidence-risk-review-plan/SKILL.md`
- `.claude/skills/copilot-pr-review-loop/SKILL.md`
- `.claude/skills/codex-pr-review-fallback/SKILL.md`
- `.github/copilot-instructions.md`
- `.github/PULL_REQUEST_TEMPLATE.md`
- `CLAUDE.md`

## Operating Rules

- Temporary review strategy override from 2026-06-14: do not launch local Copilot, GitHub Copilot, or Codex reviews for every W/subtask while completing W3-W8. Keep running local gates, PRs, merges, and CI checks. Collect review notes in `docs/PROGRESS.md`/`docs/LESSON.md`, then run one deep Copilot/Codex review over the full roadmap diff before final hardening/release. If an AI review was already running before this override, fix any valid findings already received, but do not request another pass.
- Treat this as a reusable Laravel package, not an AskMyDocs feature branch.
- The package must stay 100% standalone-agnostic: no dependency, namespace, schema, or symbol leak from AskMyDocs or sibling products.
- Target Laravel 13.x and PHP `^8.3`; local validation should include Herd PHP 8.5 when available, with CI matrix for PHP 8.3, 8.4, and 8.5.
- Keep public surfaces thin: PHP facade/API, HTTP API, Artisan, and MCP tools must call the shared `ReviewEngine`; no business logic in adapters.
- Default external capabilities are OFF: HTTP API, MCP, and LLM integration must be disabled unless the host enables them.
- Ship no hard LLM SDK dependency. The only LLM boundary is `EvidenceReviewerLlmContract`.
- Domain content belongs in config/profile data, not core logic.
- Log review failures loudly; never silently swallow persistence, profile, budget, or LLM errors.
- Update `docs/PROGRESS.md` after meaningful work.
- Update `docs/LESSON.md` when discovering setup facts, API decisions, review feedback, or test workarounds that would save the next session time.
- Ensure `phpunit.xml` includes every test family introduced by the work (`Unit`, `Feature`, `Live`, `Architecture`); a green run that omits new directories is not a valid gate.
- For Testbench config that controls provider boot or route registration, prefer `#[WithConfig(..., defer: false)]` over late environment mutation.
- OpenAPI changes must pass Redocly lint. Declare `security: []` for explicit no-auth APIs, include `info.license`, and include 4XX responses for operations.
- If Composer appears stuck, retry with `--no-interaction --no-ansi`. If even `composer --version` times out, record the blocker separately from code validation and retry on the next subtask.
- After W7, remote CI is mandatory for every PR and must pass before merge.

## Branch And PR Loop

Use a macro branch for each macro task. For each coherent subtask, create a subtask branch and PR it into the current macro branch. When the macro is complete, open the macro PR into `main`.

Planned macro branches:

- `macro/w1-foundation`
- `macro/w2-sweep-core`
- `macro/w3-llm-engine-log`
- `macro/w4-php-surface`
- `macro/w5-http-surface`
- `macro/w6-mcp-surface`
- `macro/w7-dx-docs-ci`
- `macro/w8-hardening-release`

For every subtask:

1. Define the objective, implementation details, and guardrails before editing.
2. Implement the smallest coherent slice.
3. Add or update focused PHPUnit tests. Add Vite/Vitest and Playwright only when UI/UX or browser behavior exists.
4. Run all relevant local gates.
5. While the temporary review strategy override is active, skip per-task local Copilot review and record that the deep review is deferred to the final roadmap pass. When the override is removed, run local Copilot review in report-only mode:

   ```powershell
   git diff --no-ext-diff origin/main...HEAD > "$env:TEMP\laravel-evidence-risk-review.diff"
   Get-Content -Raw "$env:TEMP\laravel-evidence-risk-review.diff" | copilot -p "/review The full diff is provided on stdin. REPORT ONLY. Do not edit files. Do not run shell commands. Do not stage files. Do not commit. Focus on correctness, tests, security, Laravel package conventions, standalone-agnostic boundaries, default-OFF behavior, and missing edge cases. Return either 'no findings' or a concise numbered list of actionable findings."
   ```

   Pass the full diff of the branch against `origin/main`, not only uncommitted files. Use stdin without `--autopilot` by default because it avoids the mutating/timeouting behavior observed with file-path `--autopilot` review. Do not use `--yolo` for local review. The prompt must explicitly say: report findings only; do not edit files; do not run shell commands; do not stage files; do not commit; return either `no findings` or a concise numbered list of actionable findings.

   If the command does not return within 5 minutes, stop it and record the blocker in `docs/PROGRESS.md` with the exact timeout duration. If local Copilot review fails three consecutive times for the same subtask because of quota, timeout, or CLI failure, record all three attempts, proceed with the PR loop, and retry local Copilot review on the next macro/subtask. This exception applies only to local Copilot review; it does not bypass local tests, remote GitHub Copilot Code Review, or CI.

   After every local Copilot review, run `git status` and inspect any diff before staging. Copilot CLI can still attempt filesystem edits despite report-only instructions; keep only intentional changes.

6. Loop until local tests are green.
7. Push and open a PR into the macro branch.
8. While the temporary review strategy override is active, do not request per-PR Copilot/Codex review. Wait for CI only when CI exists; before W7, verify no required checks are failing or pending.
9. Fix failing checks, update `docs/LESSON.md` when something useful is learned, push, and repeat until green.
10. Merge when local gates are green and remote CI has no failing or pending required checks.

Do not fake unavailable remote steps. If GitHub/CI access is blocked, record the exact blocker and the next required remote action in `docs/PROGRESS.md`.

CI note: the GitHub Actions workflow is introduced in W7. For Bootstrap and W1-W6, the remote CI gate means no required checks are failing or pending. After W7, every PR must wait for the configured workflow to pass.

## GitHub Copilot Code Review

Prefer requesting Copilot at PR creation time:

```powershell
gh pr create --base <base-branch> --head <head-branch> --title "<title>" --body-file <body-file> --reviewer copilot
```

For an existing PR, first try:

```powershell
gh pr edit <PR> --add-reviewer '@copilot'
```

Quote `@copilot` in PowerShell so the shell does not parse `@`. If GitHub answers `Could not resolve user with login 'copilot'` or the command no-ops after verification, try the GitHub CLI bot login:

```powershell
gh pr edit <PR> --add-reviewer copilot-pull-request-reviewer
```

As a last API attempt, only if the CLI forms fail or no-op:

```powershell
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/requested_reviewers --method POST -f reviewers[]='copilot-pull-request-reviewer[bot]'
```

After requesting the review, always verify that it really started:

```powershell
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/requested_reviewers
gh pr view <PR> --json reviewRequests,reviews,comments,reviewDecision,statusCheckRollup
```

Copilot has started only if Copilot appears as a pending reviewer, review, or comment. If CLI/API/GraphQL returns success but `requested_reviewers`, `reviewRequests`, reviews, and comments stay empty after polling, Copilot Code Review did not start; check repository or organization Copilot Code Review settings, enable automatic review rulesets where appropriate, or request Copilot manually from the GitHub UI Reviewers menu.

After every fix push, request or re-request Copilot review again unless an automatic review-on-push ruleset is visibly producing a fresh review. Copilot has responded only when a Copilot review/comment is visible; prefer review/comment `commit_id` matching the current `headRefOid`. Read all feedback surfaces:

```powershell
gh pr view <PR> --json headRefOid,reviewRequests,reviews,comments,reviewDecision,statusCheckRollup
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/reviews
gh api repos/padosoft/laravel-evidence-risk-review/issues/<PR>/comments
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/comments
```

For unresolved/outdated thread state, use the GraphQL `reviewThreads` query documented in `.claude/skills/copilot-pr-review-loop/SKILL.md`. Do not rely on `reviewDecision` alone; Copilot normally leaves comment reviews.

## Codex Connector Fallback

Copilot is the first remote AI review source. If Copilot is blocked by quota, budget, access, or prolonged non-response after the documented request, ruleset, push, and verification attempts, switch automatically to ChatGPT Codex Connector for that PR cycle.

Use:

```powershell
gh pr comment <PR> --body '@codex review'
```

Verify through `gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/reviews` and PR comments. The fallback review has responded only when `chatgpt-codex-connector[bot]` appears as a review/comment/reaction. Prefer a Codex review whose `Reviewed commit:` matches the current `headRefOid` prefix. Fix actionable findings, push, and comment `@codex review` again until clear.

## Copilot Reviewer Fallback

Prefer:

```text
gh pr edit <PR> --add-reviewer '@copilot'
gh pr edit <PR> --add-reviewer copilot-pull-request-reviewer
```

If GitHub CLI fails before requesting the review because it queries PR project items and the token lacks `read:project`, resolve the PR node ID:

```text
gh pr view <PR> --json id
```

Then request the Copilot Code Review bot through GraphQL:

```powershell
$query = @'
mutation RequestReviewsByLogin($pullRequestId: ID!, $botLogins: [String!], $union: Boolean!) {
  requestReviewsByLogin(input: {pullRequestId: $pullRequestId, botLogins: $botLogins, union: $union}) {
    clientMutationId
  }
}
'@
gh api graphql -f query="$query" -F pullRequestId='<PR_NODE_ID>' -F botLogins[]='copilot-pull-request-reviewer[bot]' -F union=true
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/requested_reviewers
```

The REST `requested_reviewers` endpoint can return success without creating a visible Copilot Code Review request. Treat it as a request attempt only; the gate is the verification output.

## Background Agent Strategy

- Use high-reasoning backend workers only for disjoint, well-scoped package/security/contracts slices.
- Use frontend/UI workers only if a later task adds browser-facing UI or docs screenshots.
- Keep one main integrator responsible for reading `docs/LESSON.md`, merging worker output, resolving conflicts, and running the final gates.
- Do not run broad parallel workers over the same files.

## Current Priority

Continue W3 on `task/w3-llm-engine-log`; finish PR #7 into `macro/w3-llm-engine-log` without launching another per-PR review, then proceed through W4-W8 with the temporary final-deep-review strategy.
