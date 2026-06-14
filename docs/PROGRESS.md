# Progress

## 2026-06-13

- Received the package implementation request and process constraints.
- Confirmed current repo is on `main` tracking `origin/main` and initially contains only `.git`, `LICENSE`, and stub `README.md`.
- Read the canonical spec from `%USERPROFILE%\Downloads\padosoft-laravel-evidence-risk-review-SPEC-PLAN.md`.
- Read the prior `product_image_discovery_admin` project guide files:
  - `AGENTS.md`
  - `docs/RULES.md`
  - `docs/LESSON.md`
  - `docs/PROGRESS.md`
  - `skills/product-image-discovery-admin-plan/SKILL.md`
- Created and switched to bootstrap branch `task/bootstrap-agent-rules`.
- Created the first required durable process files before application code:
  - `AGENTS.md`
  - `CLAUDE.md`
  - `docs/RULES.md`
  - `docs/LESSON.md`
  - `docs/PROGRESS.md`
  - `docs/IMPLEMENTATION_PLAN.md`
  - `skills/laravel-evidence-risk-review-plan/SKILL.md`
  - `skills/laravel-evidence-risk-review-plan/references/plan-location.md`
  - `.claude/skills/laravel-evidence-risk-review-plan/SKILL.md`
  - `.claude/rules/laravel-evidence-risk-review.md`
- Local sanity check passed: `git diff --check`.
- Generated full staged bootstrap diff at `%TEMP%\laravel-evidence-risk-review-bootstrap.diff`.
- Attempted local Copilot review with `copilot --autopilot --yolo -p "/review ..."` against the generated diff. The CLI did not return within 120 seconds, still had a running `copilot` process after timeout, and was stopped after an additional 60-second wait. This gate is therefore blocked by local Copilot CLI timeout, not completed.
- Created local bootstrap commit `docs: add agent bootstrap plan` on `task/bootstrap-agent-rules`; amended as bootstrap notes were refined.
- Verified `copilot --version` responds with GitHub Copilot CLI `1.0.61`.
- Retried local Copilot review with the committed branch diff (`git diff origin/main...HEAD`) and a shorter prompt. The CLI again timed out after 180 seconds and left a running `copilot` process, which was stopped. Local Copilot review remains blocked.
- The timed-out Copilot `--yolo` run still applied useful local edits before hanging: added fallback instructions when the external spec file is unavailable, added a formal Bootstrap milestone/exit gate, added sync notes between the two skill locations, and created `.gitignore`. These edits were reviewed locally and kept, but they do not count as a completed Copilot review.

## Open Items

- Run a fresh final local Copilot report-only review after the latest guardrail wording normalization; push/open PR only if it returns zero actionable findings.
- After bootstrap is merged, start `macro/w1-foundation` and its first subtask branch.

## 2026-06-13 (follow-up)

Applied fixes from local Copilot review of bootstrap diff:

- Added fallback instruction to `AGENTS.md` and `CLAUDE.md` for the hardcoded Windows spec path; any agent on a different machine will now continue from `docs/IMPLEMENTATION_PLAN.md`.
- Added `.gitignore` covering `vendor/`, `node_modules/`, `.env`, `*.diff`, coverage, and caches.
- Added a sync note to `.claude/skills/laravel-evidence-risk-review-plan/SKILL.md` clarifying it is the summary version and pointing to the full `skills/` counterpart.
- Added a formal `Bootstrap` exit gate section to `docs/IMPLEMENTATION_PLAN.md` before the W1 milestone.

Applied fixes from Copilot CLI review of bootstrap diff (4 findings):

1. Added fallback ("If unavailable, continue from `docs/IMPLEMENTATION_PLAN.md`") to `docs/RULES.md` Source Of Truth section — it was the only file missing the fallback.
2. Added `docs/PROGRESS.md` and `docs/LESSON.md` update steps to the Bootstrap exit gate in `docs/IMPLEMENTATION_PLAN.md` for consistency with the generic subtask exit gate template.
3. Added 5-minute timeout fallback to the local Copilot review loop in both `AGENTS.md` and `docs/IMPLEMENTATION_PLAN.md`: stop the process, manual self-review, record blocker, continue.
4. Marked the Bootstrap milestone in `docs/IMPLEMENTATION_PLAN.md` as the only branch targeting `main` directly; all W1+ work follows subtask → macro → main.

User noticed the Copilot CLI review ran in `--yolo` mode and applied fixes/amended the commit instead of only reporting. Adjusted the durable review instructions:

- Local Copilot review is now documented as report-only mode, without `--yolo` by default.
- Review prompts must explicitly say: do not edit files, do not run shell commands, do not commit, report findings only.
- Timeout fallback no longer says to continue to push/PR. It now says to record the blocker and avoid push/PR unless Copilot review later completes with zero actionable comments or the user explicitly approves a manual-review exception.

Ran local Copilot report-only review with no `--yolo`. It returned 8 actionable findings and did not modify files directly. Applied fixes:

- Added report-only local Copilot guardrails to `docs/RULES.md`.
- Added report-only local Copilot guardrails and reciprocal `.claude/skills` sync note to `skills/laravel-evidence-risk-review-plan/SKILL.md`.
- Added explicit fallback wording to `skills/laravel-evidence-risk-review-plan/references/plan-location.md`.
- Added Bootstrap/W1-W6 CI-gate clarification to `AGENTS.md` and `docs/IMPLEMENTATION_PLAN.md`.
- Added `AGENTS.md` Current Priority update to the Bootstrap exit gate and updated the current priority text.
- Added "Plus all Subtask Exit Gate requirements above" to W1-W8 milestone exit gates.
- Added lessons about redundant process guardrails and pre-W7 CI semantics.

Applied fixes from second local Copilot report-only review (3 findings):

1. Cleared stale "Re-run local Copilot /review gate" from Open Items — superseded by the follow-up entry that confirms 8 findings were applied.
2. Added report-only guardrail to `.claude/rules/laravel-evidence-risk-review.md`: no `--yolo`, prompt must say not to edit/commit.
3. Added report-only guardrail to `CLAUDE.md` Review Checklist for consistency with `AGENTS.md`, `docs/RULES.md`, and `skills/` SKILL.md.

Note: despite the report-only prompt, Copilot attempted to edit files through shell/.NET IO. The resulting working-tree diff was inspected manually before staging.

Applied fixes from third local Copilot report-only review (4 findings + 1 minor note):

1. Updated Open Items to state the current gate: run a fresh final report-only review before push/PR.
2. Added report-only Copilot guardrail to `.claude/skills/laravel-evidence-risk-review-plan/SKILL.md`.
3. Added post-review `git status` and diff-inspection instruction to `AGENTS.md`.
4. Clarified the Bootstrap `AGENTS.md` Current Priority update is pre-included in the Bootstrap PR, not a direct post-merge commit to `main`.
5. Replaced concrete user-profile paths with `%USERPROFILE%\...` paths in public docs.

Ran local Copilot review without `--autopilot`, passing the diff via stdin. It returned 3 actionable findings and did not mutate files. Applied fixes:

1. Added an explicit "Do not use `--yolo`" sentence to `AGENTS.md` step 5.
2. Changed `AGENTS.md` Current Priority to be W1-ready on `main`: `Start macro/w1-foundation and its first subtask branch.`
3. Added "with the exact timeout duration" to the `AGENTS.md` timeout-recording instruction.

Ran another local Copilot review without `--autopilot`, passing the diff via stdin. It returned 3 actionable findings about incomplete propagation of the same review guardrails. Applied fixes:

1. Added the `return only findings or no findings` clause to both skill files.
2. Added post-review `git status` / diff-inspection requirements to `docs/RULES.md`, `docs/IMPLEMENTATION_PLAN.md`, both skill files, `.claude/rules`, and `CLAUDE.md`.
3. Added timeout/no-push-until-clean-review semantics to both skill files, `.claude/rules`, `docs/RULES.md`, and `CLAUDE.md`.

Ran another local Copilot review via stdin/no-autopilot. It returned 3 actionable findings. Applied fixes:

1. Added the `return only findings or no findings` return-contract clause to `CLAUDE.md`.
2. Completed the `CLAUDE.md` timeout instruction with "stop it", "exact timeout duration", and `docs/PROGRESS.md`.
3. Updated the canonical local Copilot review command in `AGENTS.md` and `docs/IMPLEMENTATION_PLAN.md` to use stdin without `--autopilot`, matching the approach that works reliably in this repo.
4. Propagated the stdin/no-autopilot review mode note to `docs/RULES.md`, `CLAUDE.md`, both skill files, and `.claude/rules`.

Ran another local Copilot review via stdin/no-autopilot. It returned 3 actionable wording findings. Applied fixes:

1. Added `do not stage files` to every prose description of the report-only prompt.
2. Normalized the return contract everywhere to `no findings` or a concise numbered list of actionable findings.
3. Updated Open Items to point at the current final review gate.

Ran another local Copilot review via stdin/no-autopilot with the canonical prompt. It returned 2 actionable wording findings. Applied fixes:

1. Updated `docs/LESSON.md` to include `not to stage files` in the local Copilot review prompt guardrail.
2. Replaced loose `clean review completes` timeout wording with `completes with zero actionable comments` in `CLAUDE.md`, `docs/RULES.md`, both skill files, and `.claude/rules`.

Ran another local Copilot review via stdin/no-autopilot. It returned 2 actionable consistency findings. Applied fixes:

1. Added the same focus-area clause from `docs/IMPLEMENTATION_PLAN.md` to the canonical command in `AGENTS.md`.
2. Propagated the "Copilot CLI can still attempt filesystem edits" warning to `CLAUDE.md`, `docs/RULES.md`, both skill files, and `.claude/rules`.

Ran another local Copilot review via stdin/no-autopilot. It returned 2 actionable propagation findings. Applied fixes:

1. Added the pre-W7/post-W7 CI gate nuance to `CLAUDE.md` and `docs/RULES.md`.
2. Added the focus-area clause to both skill files.

Ran another local Copilot review via stdin/no-autopilot. It returned 4 actionable findings. Applied fixes:

1. Added the focus-area clause to `CLAUDE.md`, `docs/RULES.md`, and `.claude/rules`.
2. Added pre-W7/post-W7 CI gate nuance to both skill files and `.claude/rules`.
3. Completed the `docs/IMPLEMENTATION_PLAN.md` filesystem-edits warning with `keep only intentional changes`.
4. Removed the full local path of the prior sibling project from `docs/PROGRESS.md`, keeping only the project name.

Attempted the next final local Copilot review via stdin/no-autopilot after the current bootstrap commit. The CLI returned:

```text
402 additional_spend_limit_reached
```

Local Copilot review gate is blocked by Copilot plan quota. Per project rules, do not push/open PR until the review later completes with zero actionable comments or the user explicitly approves a manual-review exception for this bootstrap task.

Retried the final local Copilot review via stdin/no-autopilot after commit `fdc1a06`. The CLI again returned:

```text
402 additional_spend_limit_reached
```

The bootstrap branch remains blocked on Copilot plan quota. Working tree and `git diff --check origin/main...HEAD` were clean before the retry.

Retried the final local Copilot review via stdin/no-autopilot after commit `a64c295`. The CLI again returned:

```text
402 additional_spend_limit_reached
```

This is the third consecutive goal turn blocked by the same Copilot plan quota condition. Bootstrap remains ready locally but cannot proceed to push/PR under the documented rules without either restored Copilot quota or explicit user approval for a manual-review exception.

User explicitly approved a manual-review exception for the Bootstrap local Copilot quota blocker and asked to proceed with PR.

- Confirmed branch `task/bootstrap-agent-rules` is clean except for unrelated untracked local artifacts.
- Found `.codex-openai-docs-cache/` generated locally and added it to `.gitignore`.
- Found `resources/screenshots/laravel-evidence-risk-review-admin-Dashboard-dark.png` untracked; left it uncommitted because it is outside the Bootstrap docs scope and may be used later for the final README.
- Pushed `task/bootstrap-agent-rules` and opened PR #1: `https://github.com/padosoft/laravel-evidence-risk-review/pull/1`.
- Requested Copilot Code Review with `gh pr edit 1 --add-reviewer '@copilot'`; command returned success but `reviewRequests` remained empty.
- Used GraphQL `requestReviewsByLogin` fallback with `copilot-pull-request-reviewer[bot]`; mutation returned success but `reviewRequests` remained empty.
- Retried GraphQL with `copilot-pull-request-reviewer`, `copilot`, `github-copilot[bot]`, and `github-copilot`. The first two returned success with no visible review request; the latter two returned `Could not resolve bot`.
- Checked PR #1 after waiting: no comments, no reviews, no review requests, no checks. Pre-W7 no-checks state is expected, but Copilot Code Review is not visibly started through API and likely needs manual GitHub UI intervention or repo-side Copilot setup.

User clarified process updates after reviewing `padosoft-laravel-flow`:

- Local Copilot CLI review is exempt after three consecutive failures for the same subtask; record attempts, proceed with PR, retry local Copilot on the next macro/subtask.
- Remote GitHub Copilot Code Review remains mandatory.
- Correct Copilot PR engagement should prefer `gh pr create --reviewer copilot`; for existing PRs use `gh pr edit <PR> --add-reviewer copilot` without `@`.
- A successful command or GraphQL mutation is not enough; verify Copilot appears in `requested_reviewers`, `reviewRequests`, reviews, or comments.
- Added `.claude/skills/copilot-pr-review-loop/SKILL.md` and updated AGENTS/CLAUDE/RULES/IMPLEMENTATION_PLAN/LESSON with this procedure.

Retried PR #1 Copilot engagement after importing the laravel-flow procedure:

- `gh pr edit 1 --add-reviewer copilot` failed in this repo with `Could not resolve user with login 'copilot'`.
- `gh pr edit 1 --add-reviewer copilot-pull-request-reviewer[bot]` also failed through GraphQL.
- `gh pr edit 1 --add-reviewer copilot-pull-request-reviewer` returned the PR URL, but after polling `requested_reviewers`, `reviewRequests`, `reviews`, and comments were still empty.
- REST `requested_reviewers` with `reviewers[]=copilot-pull-request-reviewer[bot]` returned HTTP 200 with the PR payload, but `requested_reviewers` remained empty.
- Repo rulesets endpoint returned `[]`, so no automatic Copilot review ruleset is configured. PR #1 is still blocked on remote Copilot Code Review visibly starting; likely next action is GitHub UI Reviewers -> Copilot or repository/organization Copilot Code Review settings.

Re-read `padosoft-laravel-flow` Copilot workflow files to fold in the complete review loop:

- Source files read: `.claude/skills/copilot-pr-review-loop/SKILL.md`, `.claude/rules/rule-pr-workflow.md`, `AGENTS.md`, `docs/RULES.md`, `docs/LESSON.md`, `docs/PROGRESS.md`, `.github/copilot-instructions.md`, and `.github/PULL_REQUEST_TEMPLATE.md`.
- Added missing details here: re-request review after every fix push, poll timing, how to verify Copilot actually answered, all feedback surfaces to inspect, GraphQL review thread state, and the rule that `docs/PROGRESS.md` stays a durable handoff summary rather than a per-poll log.

After pushing the updated bootstrap docs to PR #1, re-ran the documented Copilot request sequence:

- `gh pr edit 1 --add-reviewer copilot` still fails with `Could not resolve user with login 'copilot'`.
- `gh pr edit 1 --add-reviewer copilot-pull-request-reviewer` returns the PR URL, but after waiting and checking `requested_reviewers`, PR JSON reviews/comments, reviews API, issue comments API, inline comments API, and GraphQL reviewThreads, Copilot is still not visible.
- Durable blocker remains: GitHub UI Reviewers -> Copilot or repository/organization Copilot Code Review settings are required before PR #1 can satisfy the remote Copilot gate.

Found the authenticated GitHub token has admin permission on `padosoft/laravel-evidence-risk-review`. Created repository ruleset `Copilot code review for PRs` (`id 17646623`) with rule type `copilot_code_review` and `review_on_push=true`. Initially targeted `~DEFAULT_BRANCH` and `refs/heads/macro/*`; later tested `refs/heads/*`, which did not match slash-containing branches in the branch-rules API. Final ruleset targets `~DEFAULT_BRANCH`, `refs/heads/main`, `refs/heads/task/*`, and `refs/heads/macro/*`; API confirms the rule applies to both `main` and URL-encoded `task/bootstrap-agent-rules`.

After the ruleset was created, pushed updated bootstrap docs to PR #1 and waited. Automatic review still did not become visible in requested reviewers, PR review JSON, reviews API, issue comments, inline comments, or GraphQL reviewThreads. Retried `gh pr edit 1 --add-reviewer '@copilot'`; GitHub CLI accepted the command but the same verification surfaces remained empty. Added `.github/copilot-instructions.md` and updated process docs to prefer quoted `@copilot` with strict verification.

Closed and reopened PR #1 to trigger the newly configured automatic review rule. After waiting and checking all review surfaces, Copilot still did not appear. Repository branch-rules API confirms the `copilot_code_review` rule applies to `main`; repo rule-suite endpoints returned no useful evaluation records.

Closed PR #1 as superseded and opened fresh PR #2 (`https://github.com/padosoft/laravel-evidence-risk-review/pull/2`) after the ruleset was corrected. PR #2 was created with `--reviewer '@copilot'`; after waiting and checking requested reviewers, PR review JSON, reviews API, issue comments API, inline comments API, and status checks, Copilot still did not appear. The remaining blocker is not branch targeting or GitHub CLI syntax: it requires GitHub UI Reviewers -> Copilot, Copilot Code Review org/repo access, or Copilot premium quota/settings to be fixed externally.

User approved continuing despite Copilot non-response and instructed that Copilot remains the first source, but prolonged out-of-budget/non-response should automatically switch to ChatGPT Codex Connector. Read `padosoft/scalar-openapi-doc` PR #16: the working trigger is commenting `@codex review`; `chatgpt-codex-connector[bot]` then leaves PR reviews with `Codex Review` and `Reviewed commit:`. Added `.claude/skills/codex-pr-review-fallback/SKILL.md` and propagated the fallback rule to AGENTS, CLAUDE, RULES, plan, and skills.

Triggered Codex fallback on PR #2 with `@codex review`. Codex responded on commit `3c47c8373d` with one P2 finding: `docs/RULES.md` still contained a contradictory ban on substituting Codex for Copilot. Updated the rule to allow Codex only for the documented fallback or explicit user request.

Re-ran Codex fallback after that fix. Codex responded on commit `699c24bc54` with one P2 finding: `docs/RULES.md` declared MIT while the repo ships an Apache-2.0 `LICENSE`. Updated the rule to Apache-2.0 and recorded the lesson for W1 composer/README metadata.

Codex fallback final pass on PR #2 returned no major issues for commit `cee7d9f`; PR #2 was merged to `main` as merge commit `75b0e89`.

## 2026-06-14

- Started W1 Foundation on macro branch `macro/w1-foundation` and subtask branch `task/w1-foundation-scaffold`.
- User confirmed the remote review fallback policy: Copilot remains the first review source; if Copilot is blocked by budget/quota or prolonged non-response, automatically switch to ChatGPT Codex Connector by commenting `@codex review` and verifying `chatgpt-codex-connector[bot]` responded on the current head commit.
- Verified local toolchain for W1: Herd PHP `8.5.7` and Composer `2.9.7`.
- W1 subtask objective: Composer/Testbench scaffold, package service provider/config, core enums, immutable DTOs, `EvidenceTierValue`, `TierResolver`, cheap-only `EvidenceTierLabeler`, and standalone architecture guardrail.
- W1 subtask guardrails: `composer validate --strict`, dependency install/update, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, and `vendor/bin/phpunit`. No UI is included in W1, so Vite and Playwright are not applicable for this subtask.
- Implemented W1 scaffold and core code: `composer.json`, `phpunit.xml`, `pint.json`, `phpstan.neon.dist`, default-off config, service provider, enums, immutable DTOs, `EvidenceTierValue`, `TierResolver`, and cheap-only `EvidenceTierLabeler`.
- Added W1 tests: enum/rank/verdict ordering, DTO array round-trip, tier value validation, resolver custom tier/rerank/order behavior, labeler declared tier/hints/DOI/arXiv/unverified behavior, and standalone architecture scan over `src/` plus Composer dependency keys.
- Local gates passed after formatting:
  - `composer validate --strict`
  - `composer install --no-progress --prefer-dist`
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse --memory-limit=512M`
  - `vendor/bin/phpunit`
- Plain `vendor/bin/phpstan analyse` exhausted Herd PHP's 128M memory limit before reporting type errors; the local W1 gate uses `--memory-limit=512M` and this lesson is recorded in `docs/LESSON.md`.
- Local Copilot report-only review attempts for W1 Foundation failed three consecutive times with `402 additional_spend_limit_reached` while reviewing `%TEMP%\laravel-evidence-risk-review-w1-foundation.diff`. Per user-approved local policy, this subtask is exempt from the local Copilot gate and will proceed to PR review; retry local Copilot on the next subtask/macro.
- Opened subtask PR #3 from `task/w1-foundation-scaffold` into `macro/w1-foundation`. Copilot reviewer requests and repository ruleset did not produce visible Copilot review/comment/request surfaces, so Codex fallback was triggered with `@codex review`.
- Codex fallback reviewed commit `4784d7d866` and found two P2 issues in `EvidenceTierLabeler`: invalid matching tier hints silently fell back to `unverified`, and DOI detection over-ranked arXiv sources before the preprint branch. Both fixes are being applied with dedicated tests.
- Fixed both Codex findings, reran local gates, amended/pushed PR #3 at commit `7caa68a`, and re-triggered Codex fallback. Codex responded: `Didn't find any major issues` with `Reviewed commit: 7caa68ab91`. The subtask PR is ready to merge into `macro/w1-foundation`; no CI checks exist yet before W7.
- After updating `docs/PROGRESS.md`, PR #3 was amended to commit `b693b5d` and Codex was re-triggered. Codex found one additional P2 issue: DTO `list<>` fields accepted associative arrays and silently normalized them. Fixing `source_ids`, `claims`, and `sources` with `array_is_list()` guards plus regression tests.
- Fixed the DTO list-shape issue, reran local gates, amended/pushed PR #3 at commit `d45b107`, and re-triggered Codex fallback. Codex responded: `Didn't find any major issues` with `Reviewed commit: d45b1079f3`. PR #3 was merged into `macro/w1-foundation` as merge commit `7a576f5`.
- While validating the macro branch after merge, `vendor/bin/pint --test` failed only on CRLF `line_ending` fixers introduced by Windows checkout/merge. Added `.gitattributes` to enforce LF for source/config/docs/test files and re-ran Pint normalization before opening the macro PR.
- Opened macro PR #4 from `macro/w1-foundation` into `main`; Copilot remained invisible, so Codex fallback was triggered. Codex reviewed commit `8769c01573` and found one P2 issue: explicit `null` list fields were converted to empty arrays by `?? []`. Fixing with `array_key_exists()` defaults and explicit-null regression tests.
- After fixing explicit null list fields, Codex reviewed commit `db5009bc84` and found one more P2 issue: built-in tier config overrides with `rank => null` or `label => null` inherited enum defaults instead of failing. Fixing with `array_key_exists()` defaults and regression tests.
- Fixed the null tier override issue, reran local gates, pushed commit `6798338`, and re-triggered Codex fallback on PR #4. Codex responded: `Didn't find any major issues` with `Reviewed commit: 6798338fc4`. PR #4 was merged into `main` as merge commit `d96d650`.

## 2026-06-14 (W2)

- Started W2 Sweep Core from `main` after W1 merge.
- Created macro branch `macro/w2-sweep-core` and subtask branch `task/w2-sweep-core`.
- W2 subtask objective: implement `RiskCheck` contract, built-in cheap checks, `RiskSweepEngine`, verdict reduction, profile contract/config/registry, built-in profile config files, `ReviewBudget`, `BudgetMeter`, and budget skip findings.
- W2 guardrails: `composer validate --strict`, `composer install --no-progress --prefer-dist` if dependencies change, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=512M`, `vendor/bin/phpunit`, standalone architecture test. No UI is included in W2, so Vite and Playwright are not applicable.
- Implemented W2 sweep core on `task/w2-sweep-core`: profile contract/registry/config profiles, budget value/meter, review finding/result/options DTOs, cheap built-in checks, risk sweep engine, provider bindings, and config publishing for nested profile files.
- Added W2 tests covering budget caps, profile loading/validation, service-provider sweep engine resolution, all built-in cheap check kinds, verdict reduction, cheap-before-heavy ordering, over-budget heavy skip findings, and duplicate check registration rejection.
- Local W2 gates passed:
  - `composer validate --strict`
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
  - `vendor/bin/phpunit`
- `composer install` was not rerun because W2 did not change Composer dependencies. No UI is included in W2, so Vite and Playwright remain not applicable.
- Local Copilot report-only review for `%TEMP%\laravel-evidence-risk-review-w2-sweep-core.diff` failed three consecutive times with `402 additional_spend_limit_reached`. Per user-approved local policy, W2 is exempt from the local Copilot gate and proceeds to PR review; retry local Copilot on the next subtask/macro.
- Opened subtask PR #5 from `task/w2-sweep-core` into `macro/w2-sweep-core`. Copilot reviewer requests remained invisible, so Codex fallback was triggered with `@codex review`.
- Codex reviewed commit `cb43ad3237` and found three P2 issues: env-driven budget values were strings rejected by `ReviewBudget`, missing profile `min_tier` entries silently downgraded assertiveness requirements to `unverified`, and malformed keyword settings silently disabled keyword checks. Fixed all three with regression tests.
- After Codex fixes, local gates passed again:
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
  - `vendor/bin/phpunit`
- Codex reviewed updated commit `4b84fe326a` and found two additional P2 issues: explicit `verdict => null` still defaulted through `??`, and keyword substring matching made `improves` match `proves`. Fixed both with regression tests.
- Codex reviewed updated commit `377054113a` and found one additional P2 issue: unknown profile `checks` keys such as `red_flags` were accepted and later ignored. Fixed by validating settings keys against `RiskCheckKind` with a regression test.
- Codex reviewed updated commit `ba01d0f916` and found two additional P2 issues: enabled keyword-backed checks could omit `keywords` and become no-ops, and vendor publishing copied package profiles into generic host `config/profiles`. Fixed by requiring keywords for enabled keyword-backed checks and publishing profiles under package-specific `config/evidence-risk-review/profiles`.
- Codex fallback final pass for PR #5 responded with `Didn't find any major issues` on commit `3e908ac64b`. PR #5 was merged into `macro/w2-sweep-core` as merge commit `70fa9e1`.
- Macro W2 local gates passed on `macro/w2-sweep-core` after the subtask merge:
  - `composer validate --strict`
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
  - `vendor/bin/phpunit`
- Local Copilot report-only macro review for `%TEMP%\laravel-evidence-risk-review-w2-macro.diff` failed three consecutive times with `402 additional_spend_limit_reached`. Per user-approved local policy, the W2 macro PR proceeds to remote review; retry local Copilot on the next macro/subtask.
- Opened macro PR #6 from `macro/w2-sweep-core` into `main`. Copilot stayed invisible after reviewer attempts, so Codex fallback was triggered with `@codex review`.
- Codex fallback passed PR #6 on commit `fac88c5b55` with no major issues. PR #6 was merged into `main` as merge commit `dff0413`.

## 2026-06-14 (W3)

- Started W3 LLM Boundary, Engine, Log from `main` after W2 merge.
- Created macro branch `macro/w3-llm-engine-log` and subtask branch `task/w3-llm-engine-log`.
- W3 subtask objective: implement LLM contract/request/response/null/callback boundary, heavy LLM evidence check, `ReviewEngine`, append-only review log store contract, null/array/database log stores, and published migration.
- W3 guardrails: `composer validate --strict`, dependency resolution after adding `illuminate/database`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`, `vendor/bin/phpunit`, SQLite database log feature test, R26 short-circuit no-LLM test. No UI is included in W3, so Vite and Playwright are not applicable.
- Implemented W3 code: `EvidenceReviewerLlmContract`, `LlmRequest`, `LlmResponse`, `NullEvidenceReviewerLlm`, `CallbackEvidenceReviewerLlm`, `LlmEvidenceStrengthCheck`, `ReviewEngine`, `ReviewLogStore`, `NullReviewLogStore`, `ArrayReviewLogStore`, `DatabaseReviewLogStore`, migration stub, provider bindings, and migration publishing.
- Added W3 tests covering null/callback LLM boundary, invalid callback result, ReviewEngine no-LLM short-circuit when cheap pass clears, heavy check execution when cheap findings exist, dry-run logging skip, array log append, and SQLite database log append.
- Local W3 gates passed:
  - `composer update illuminate/database --with-all-dependencies --no-progress`
  - `composer validate --strict`
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
  - `vendor/bin/phpunit`
- Local Copilot report-only review for `%TEMP%\laravel-evidence-risk-review-w3.diff` failed three consecutive times with `402 additional_spend_limit_reached`. Per user-approved local policy, W3 proceeds to PR review; retry local Copilot on the next macro/subtask.
- User changed the review strategy: stop launching local Copilot, GitHub Copilot, or Codex reviews for every W/subtask. Continue W3-W8 with local gates, PRs, merges, and CI checks, then run one deep AI review at the end of the roadmap. PR #7 Codex review was already in flight before the change and returned three valid P2 findings; fix those, but do not request another review pass.
- Fixed the three already-received PR #7 Codex findings locally: `llm.enabled` now gates heavy LLM paths, LLM `source_tiers` refinements are applied, and unknown `review_log.store` values fail loudly instead of silently falling back to null logging.
- Post-fix local gates passed without a new AI review request:
  - `composer validate --strict`
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
  - `vendor/bin/phpunit`
- Subtask PR #7 was merged into `macro/w3-llm-engine-log` as merge commit `e7b654d`.
- W3 macro local gates passed without per-W AI review, per the temporary final-deep-review override:
  - `composer validate --strict`
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
  - `vendor/bin/phpunit`
- W3 macro PR #8 was merged into `main` as merge commit `72fe118`.

## 2026-06-14 (W4)

- Started W4 PHP Surface from `main` after W3 merge.
- Created macro branch `macro/w4-php-surface` and subtask branch `task/w4-php-surface`.
- W4 subtask objective: add the public PHP service/facade surface, array review helper, profile/taxonomy helpers, Artisan commands, command exit codes, and command/public API tests.
- Implemented W4 code: `EvidenceRiskReview` service, Laravel facade, facade alias metadata, `ReviewOptions::fromArray()`, console command registration, `evidence:review`, `evidence:profiles`, `evidence:taxonomy`, and `evidence:log`.
- Updated `phpunit.xml` to include `tests/Feature`, because feature tests existed but were not part of the default PHPUnit suite.
- W4 local gates passed without per-W AI review, per the temporary final-deep-review override:
  - `composer update illuminate/console --with-all-dependencies --no-progress`
  - `composer validate --strict --no-interaction --no-ansi`
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
  - `vendor/bin/phpunit --testdox` (`60 tests, 777 assertions`)
- Subtask PR #9 was merged into `macro/w4-php-surface` as merge commit `d04a63f`.
- W4 macro local gates passed without per-W AI review, per the temporary final-deep-review override:
  - `composer validate --strict --no-interaction --no-ansi`
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
  - `vendor/bin/phpunit` (`60 tests, 777 assertions`)
- W4 macro PR #10 was merged into `main` as merge commit `f9b3a7b`.

## 2026-06-14 (W5)

- Started W5 HTTP Surface from `main` after W4 merge.
- Created macro branch `macro/w5-http-surface` and subtask branch `task/w5-http-surface`.
- W5 subtask objective: add configurable default-OFF HTTP routes, REST controllers, request payload parsing, stable error contract, persisted review lookup, OpenAPI 3.1 YAML, and HTTP feature tests.
- Implemented W5 code: package route file, default-OFF route registration, review/profile/taxonomy/OpenAPI controllers, `ReviewPayloadRequest`, `ErrorResponse`, `ReviewLogQuery`, OpenAPI document, and README HTTP examples.
- W5 local gates passed without per-W AI review, per the temporary final-deep-review override:
  - `composer update illuminate/http illuminate/routing --with-all-dependencies --no-progress`
  - `composer validate --strict --no-interaction --no-ansi`
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
  - `vendor/bin/phpunit` (`67 tests, 900 assertions`)
  - `npx --yes @redocly/cli@latest lint resources/openapi.yaml`
