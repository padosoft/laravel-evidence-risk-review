---
name: copilot-pr-review-loop
description: Run the mandatory GitHub Copilot Code Review and CI loop for PRs in this repository. Use after opening a PR, after pushing to a PR branch, when requesting a Copilot re-review, or when fixing PR review/CI feedback.
---

# Copilot PR Review Loop

## Rule

Every PR must converge on both:

- GitHub Copilot Code Review has no outstanding actionable comments.
- Reported CI checks are green, or before W7 no required checks are failing or pending.

Local Copilot CLI review is a separate pre-push aid. If local Copilot fails three consecutive times for the same subtask because of quota, timeout, or CLI failure, record the attempts and proceed to this PR loop. Do not bypass remote GitHub Copilot Code Review.

CI green alone is not enough. Remote Copilot review, or an explicit verified absence of actionable Copilot comments after a real request attempt, is a separate merge gate.

## Open PR

Prefer requesting Copilot when creating the PR:

```powershell
gh pr create --base <base-branch> --head <head-branch> --title "<title>" --body-file <body-file> --reviewer copilot
```

For an existing PR, first try:

```powershell
gh pr edit <PR> --add-reviewer '@copilot'
```

Quote `@copilot` in PowerShell so `@` is not parsed by the shell. If GitHub answers `Could not resolve user with login 'copilot'` or the command no-ops after verification, try the GitHub CLI bot login:

```powershell
gh pr edit <PR> --add-reviewer copilot-pull-request-reviewer
```

As a last API attempt, only if the CLI forms fail or no-op:

```powershell
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/requested_reviewers --method POST -f reviewers[]='copilot-pull-request-reviewer[bot]'
```

Do not treat any accepted command as success until verification shows Copilot.

## Verify Copilot Started

Do not assume success from command exit code alone.

```powershell
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/requested_reviewers
gh pr view <PR> --json reviewRequests,reviews,comments,reviewDecision,statusCheckRollup
```

Copilot has started only if at least one of these is true:

- `requested_reviewers` shows Copilot.
- `reviewRequests` shows Copilot.
- `reviews` includes Copilot.
- `comments` includes Copilot.

If CLI/API/GraphQL returns success but all of these stay empty after polling, Copilot did not start. Check repository or organization Copilot Code Review settings, enable automatic review rulesets where appropriate, or request Copilot manually from the GitHub UI Reviewers menu.

## Enable Automatic Review Ruleset

If the repository has admin permissions and no ruleset exists, create a repository ruleset with only the `copilot_code_review` rule. Target `~DEFAULT_BRANCH`, `main`, `task/*`, and `macro/*`, and enable `review_on_push` so subtask PRs into macro branches, macro PRs into `main`, and bootstrap exception PRs all use the same automatic review policy. A broad `refs/heads/*` pattern did not match slash-containing branches in the branch-rules API; verify with URL-encoded branch names.

```powershell
$payload = @{
  name = 'Copilot code review for PRs'
  target = 'branch'
  enforcement = 'active'
  conditions = @{ ref_name = @{ include = @('~DEFAULT_BRANCH', 'refs/heads/main', 'refs/heads/task/*', 'refs/heads/macro/*'); exclude = @() } }
  rules = @(@{ type = 'copilot_code_review'; parameters = @{ review_draft_pull_requests = $false; review_on_push = $true } })
} | ConvertTo-Json -Depth 10

$path = Join-Path $env:TEMP 'laravel-evidence-risk-review-copilot-ruleset.json'
Set-Content -LiteralPath $path -Value $payload -Encoding UTF8
gh api repos/padosoft/laravel-evidence-risk-review/rulesets --method POST --input $path
gh api repos/padosoft/laravel-evidence-risk-review/rulesets
gh api 'repos/padosoft/laravel-evidence-risk-review/rules/branches/task%2Fbootstrap-agent-rules'
```

After creating or updating the ruleset, push a new commit or amended commit to the PR branch, then poll the review surfaces.

## Re-Request Review After Fixes

After pushing fixes to a PR branch, Copilot may not automatically re-review unless the repository has an automatic Copilot ruleset with review-on-push enabled. Treat every push as a new review cycle:

```powershell
gh pr view <PR> --json headRefOid,reviewRequests,reviews,comments,statusCheckRollup
gh pr edit <PR> --add-reviewer '@copilot'
gh pr edit <PR> --add-reviewer copilot-pull-request-reviewer
```

Only use the second `gh pr edit` command when the first one fails or no-ops. Then re-run the verification commands above.

If GitHub refuses or silently ignores CLI/API re-request because Copilot already reviewed the PR, use the GitHub UI Reviewers menu re-request button. Record the blocker in `docs/PROGRESS.md` only as durable handoff state; keep detailed poll history in the PR.

## Know When Copilot Responded

Wait at least 60 seconds after a push before deciding CI or review did not start. For Copilot, poll for up to 15 minutes unless the API clearly reports a blocker.

```powershell
gh pr view <PR> --json headRefOid,reviewRequests,reviews,comments,reviewDecision,statusCheckRollup
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/reviews `
  --jq '.[] | {user:.user.login,state,commit_id,body,submitted_at}'
gh api repos/padosoft/laravel-evidence-risk-review/issues/<PR>/comments `
  --jq '.[] | {user:.user.login,body,created_at}'
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/comments `
  --jq '.[] | {user:.user.login,path,line,body,commit_id}'
```

Copilot has responded only when a review or comment from a Copilot reviewer is visible. Prefer a Copilot review/comment whose `commit_id` matches the current `headRefOid`; if it references an older commit, request or wait for re-review before merging unless the current diff clearly cannot be affected.

Do not rely only on `reviewDecision`: Copilot normally leaves comment reviews and may not approve or request changes.

## GraphQL Fallback

Use only when CLI reviewer request is blocked or GitHub CLI hits `read:project` issues:

```powershell
$prNodeId = gh pr view <PR> --json id --jq .id

$query = @'
mutation RequestReviewsByLogin($pullRequestId: ID!, $botLogins: [String!], $union: Boolean!) {
  requestReviewsByLogin(input: {pullRequestId: $pullRequestId, botLogins: $botLogins, union: $union}) {
    clientMutationId
  }
}
'@

gh api graphql `
  -f query="$query" `
  -F pullRequestId="$prNodeId" `
  -F botLogins[]='copilot-pull-request-reviewer[bot]' `
  -F union=true

gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/requested_reviewers
```

The REST `requested_reviewers` write endpoint can return success without starting Copilot Code Review. Treat REST success as only an attempt; the gate is the verification output.

## Read Feedback

```powershell
gh pr view <PR> --json state,reviewDecision,mergeStateStatus,statusCheckRollup,reviews,comments,reviewRequests
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/reviews
gh api repos/padosoft/laravel-evidence-risk-review/issues/<PR>/comments
gh api repos/padosoft/laravel-evidence-risk-review/pulls/<PR>/comments
```

For thread state:

```powershell
$query = @'
query($owner:String!, $repo:String!, $number:Int!) {
  repository(owner:$owner, name:$repo) {
    pullRequest(number:$number) {
      reviewThreads(first:100) {
        nodes {
          id
          isResolved
          isOutdated
          comments(first:10) {
            nodes { author { login } path line outdated body }
          }
        }
      }
    }
  }
}
'@

gh api graphql -f query="$query" -f owner='padosoft' -f repo='laravel-evidence-risk-review' -F number=<PR>
```

Fix all actionable comments, rerun relevant local gates, push, and repeat. Never merge with unresolved must-fix comments.

GitHub can leave review threads non-outdated even after a nearby fix. Before resolving or declaring a Copilot comment addressed, inspect the current file at the referenced `path` and `line`; do not trust `isOutdated` alone.

## CI

Before W7, no workflow exists and the remote CI gate means no required checks are failing or pending.

After W7, every PR must show configured checks for the current head and those checks must pass. If a PR reports no checks after W7, inspect workflow triggers and base branch before merging.

## Progress Logging

`docs/PROGRESS.md` is a durable handoff summary, not a per-poll log. Store reusable discoveries in `docs/LESSON.md`. Keep detailed CI/Copilot iteration history in the PR body or PR comments when needed.
