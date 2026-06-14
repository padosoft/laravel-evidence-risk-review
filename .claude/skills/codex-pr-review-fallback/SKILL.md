---
name: codex-pr-review-fallback
description: Use ChatGPT Codex Connector as the automatic PR review fallback when GitHub Copilot Code Review is blocked by quota, budget, access, or prolonged non-response.
---

# Codex PR Review Fallback

## Rule

GitHub Copilot Code Review remains the primary remote AI review gate.

Switch to ChatGPT Codex Connector automatically only when Copilot is blocked by quota, budget, access, or prolonged non-response after the documented Copilot request and verification attempts.

This fallback does not bypass tests, CI, or review-comment resolution. It replaces only the unavailable remote Copilot review gate for that PR cycle.

## Trigger

The working pattern observed in `padosoft/scalar-openapi-doc` PR #16 is a PR comment:

```powershell
gh pr comment <PR> --body '@codex review'
```

Codex may also trigger when a PR is opened or marked ready if the repository has the connector configured. Do not rely on automatic triggering unless a `chatgpt-codex-connector[bot]` review is visible.

Requesting `chatgpt-codex-connector[bot]` as a GitHub reviewer is optional and may not be supported; the reliable trigger is the `@codex review` comment.

## Verify Codex Responded

Poll PR review summaries and comments:

```powershell
gh pr view <PR> --json headRefOid,reviews,comments,statusCheckRollup
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/reviews
gh api repos/padosoft/laravel-evidence-risk-review/issues/<PR>/comments
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/comments
```

Codex has responded when one of these is visible:

- a review authored by `chatgpt-codex-connector[bot]`;
- a PR/issue comment authored by `chatgpt-codex-connector[bot]`;
- a connector reaction to the `@codex review` trigger comment indicating no findings.

Prefer a Codex review whose body contains `Reviewed commit:` matching the current `headRefOid` prefix. If Codex reviewed an older commit, comment `@codex review` again after the latest push.

## Act On Feedback

If Codex leaves suggestions, treat must-fix items like Copilot must-fix items:

- fix bugs, security issues, missing tests, package-boundary violations, or correctness problems;
- add focused tests for changed behavior;
- rerun relevant local gates;
- push;
- comment `@codex review` again;
- repeat until no actionable Codex findings remain.

If Codex gives no suggestions or reacts positively, record that the Codex fallback gate passed for the current commit.

## Recordkeeping

Update `docs/LESSON.md` with reusable connector behavior. Update `docs/PROGRESS.md` only with durable handoff state, not every poll.
