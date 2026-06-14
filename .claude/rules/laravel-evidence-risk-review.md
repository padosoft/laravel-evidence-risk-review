# Laravel Evidence Risk Review Rules

- Read `AGENTS.md`, `CLAUDE.md`, `docs/IMPLEMENTATION_PLAN.md`, `docs/RULES.md`, `docs/PROGRESS.md`, and `docs/LESSON.md` before implementation.
- Keep the package standalone and AskMyDocs-free.
- Use Laravel 13 and PHP `^8.3`; validate with PHP 8.5 locally when available.
- Keep HTTP API, MCP, and LLM default-OFF.
- Put business logic in `ReviewEngine` and core services, not in controllers, commands, facades, or MCP adapters.
- Add tests for every subtask. Add Playwright only when UI/UX behavior exists.
- Keep `phpunit.xml` aligned with new test directories. Use pre-boot Testbench config such as `#[WithConfig(..., defer: false)]` for route/provider behavior. Run Redocly lint for OpenAPI and YAML lint for workflow changes.
- Temporary review strategy override from 2026-06-14: while completing W3-W8, do not launch local Copilot, GitHub Copilot, or Codex reviews for every W/subtask. Keep local gates, PRs, merges, and CI checks. Run one deep AI review before final hardening/release. If a review was already running before the override, fix valid findings already received but do not request another pass.
- Run local Copilot review against the full branch diff before pushing in report-only mode: use stdin without `--autopilot`, do not use `--yolo`; the prompt must say not to edit files, not to run shell commands, not to stage files, not to commit, to focus on correctness, tests, security, Laravel package conventions, standalone-agnostic boundaries, default-OFF behavior, and missing edge cases, and to return either `no findings` or a concise numbered list of actionable findings.
- After every local Copilot review, run `git status` and inspect any diff before staging. Copilot CLI can still attempt filesystem edits despite report-only instructions; keep only intentional changes.
- If local Copilot review fails three consecutive times for the same subtask because of quota, timeout, or CLI failure, record all three attempts, proceed with the PR loop, and retry local Copilot review on the next macro/subtask. This does not bypass tests, remote GitHub Copilot Code Review, or CI.
- Request GitHub Copilot Code Review on every PR with `gh pr create --reviewer '@copilot'` whenever possible; for existing PRs try `gh pr edit <PR> --add-reviewer '@copilot'`, then `gh pr edit <PR> --add-reviewer copilot-pull-request-reviewer` if needed. Quote `@copilot` in PowerShell. Verify Copilot is visible as a pending reviewer, review, or comment. If not visible, the review did not start.
- After every fix push, request/re-request Copilot review unless an automatic review-on-push ruleset visibly produced a fresh review. Read review summaries, issue comments, inline comments, and GraphQL reviewThreads; Copilot has responded only when a Copilot-authored review/comment is visible, preferably on the current `headRefOid`.
- If Copilot is blocked by quota, budget, access, or prolonged non-response, comment `@codex review` and verify `chatgpt-codex-connector[bot]` responds before using Codex as the fallback review gate.
- Before W7, the remote CI gate means no required checks are failing or pending. W7 introduces the GitHub Actions workflow; after that, every PR must wait for configured CI to pass.
- Update `docs/PROGRESS.md` after meaningful work.
- Update `docs/LESSON.md` with durable discoveries and review feedback.
