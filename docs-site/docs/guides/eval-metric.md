# Using the engine as an eval metric

`padosoft/eval-harness` scores a pipeline against a golden dataset. Its most
capable metric is `llm-as-judge`: it asks a model whether an answer is good.

It is also the most expensive one. A thousand rows with three repetitions is
three thousand paid calls that exist purely to grade — and the grader is itself
a model, so it is non-deterministic, it can be wrong, and it disagrees with
itself between runs.

This engine answers a narrower question — *is this answer actually supported by
the sources it cites, and does its confidence match its evidence?* — with
**deterministic checks and no provider call at all**.

So it costs nothing per row, returns the same score twice, and can run on
**every** row of **every** build, next to the judge you can only afford on some
of them.

## Setup

```bash
composer require --dev padosoft/eval-harness
```

```php
use Padosoft\EvidenceRiskReview\Eval\EvidenceRiskMetric;

$eval->dataset('rag.grounding')
    ->loadFromYaml(database_path('evals/rag.grounding.yaml'))
    ->withMetrics(['llm-as-judge', EvidenceRiskMetric::class])
    ->register();
```

The harness resolves any metric FQCN through the container, so the constructor
dependencies wire themselves. Nothing needs registering in a service provider.

## The dataset row

The answer under test is whatever the pipeline produced. Everything else — the
claims it made, the sources it cited — lives in the row's `metadata.evidence`
block, because only the dataset knows what the pipeline was supposed to have
grounded itself in:

```yaml
name: rag.grounding
samples:
  - id: refund-window
    input:
      question: 'What is the refund window for a faulty item?'
    expected_output: '30 days from delivery'
    metadata:
      tags: [policy]
      evidence:
        profile: default
        claims:
          - id: c1
            text: 'Refunds are accepted for 30 days from delivery.'
            assertiveness: definitive
            source_ids: [policy-page]
        sources:
          - id: policy-page
            url: 'https://example.test/returns'
            declared_tier: official
```

`assertiveness` is `definitive`, `likely` or `tentative`. `declared_tier` runs
from `guideline` down to `unverified` — see
[Evidence tiers](/guides/evidence-tiers).

## The score

`ReviewResult::$riskScore` runs 0 (nothing found) to 1 (something must be
removed). An eval metric scores the other way round:

```
score = 1 − risk_score
```

With the harness's 0.5 pass threshold, that puts the line between verdicts:

| Highest verdict | risk | metric score | passes? |
| --- | --- | --- | --- |
| `keep` | 0.00 | 1.00 | ✅ |
| `soften` | 0.33 | 0.67 | ✅ |
| `flag_for_human_review` | 0.67 | 0.33 | ❌ |
| `remove` | 1.00 | 0.00 | ❌ |

In words: **a hedge-worthy overstatement is a note; an answer that needs a human
is a failure.** If a dataset disagrees, move the line:

```php
// Anything short of a clean review fails this dataset.
new EvidenceRiskMetric($app, minScore: 1.0)
```

A `minScore` makes the metric binary on purpose — some datasets want partial
credit for a softenable overstatement, and some want a line. Neither is right
for both.

## Rows nobody has annotated yet

A row with no `metadata.evidence` block scores **1.0**, and says so:

```json
{ "reviewed": false, "reason": "No metadata.evidence block on this row: nothing was claimed, so nothing is ungrounded." }
```

That is deliberate. Failing every un-annotated row would make the metric
impossible to adopt on a dataset that already exists — you would have to
annotate all of it before you could run any of it. An empty block (`claims: []`,
`sources: []`) counts as no block, so an annotation somebody started and did not
finish does not silently become a verdict.

## What lands in the report

Every finding is carried into the metric details, which means it reaches the
harness's JSON report and its
[run briefing](https://github.com/padosoft/eval-harness) — where the `reason` is
the line that actually explains the failure:

```json
{
  "reviewed": true,
  "review_id": "rev_01J…",
  "profile_key": "default",
  "risk_score": 0.667,
  "groundedness": 0.333,
  "max_verdict": "flag_for_human_review",
  "findings": [
    {
      "check": "evidence_strength",
      "claim_id": "c1",
      "verdict": "flag_for_human_review",
      "reason": "Definitive claim rests on an unverified source.",
      "suggested_rewrite": "One forum thread reports that …"
    }
  ]
}
```

A verdict is a label; the reason is the diagnosis. `suggested_rewrite`, when the
check produces one, is a concrete fix somebody can apply.

## Two things the metric deliberately does

**Reviews from an eval never reach the audit log.** Every review runs with
`dry_run: true`, so a CI job does not write thousands of synthetic rows into the
log a compliance team reads. That log records what production did; an eval is
not production.

**A malformed evidence block raises, it does not score zero.** A missing claim
`id` or an unknown profile key is a broken *dataset row*, and scoring it as
"ungrounded" would blame the pipeline for the harness's own input. The harness
records it as a metric failure, names the row, and leaves the score out of the
aggregate:

```
Row 'refund-window' could not be reviewed: Missing required key [id] …
```

## Where it fits next to a judge

| | `evidence-risk` | `llm-as-judge` |
| --- | --- | --- |
| Cost per row | none | a provider call |
| Deterministic | yes | no |
| Answers | is this grounded in what it cites? | is this a good answer? |
| Run it on | every row, every build | a sampled subset, or the rows that matter |

They are complementary. A pipeline can be perfectly grounded and still answer
the wrong question; it can also answer beautifully while inventing its
citations. The cheap one catches the second, every time, for free.
