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

A threshold outside `[0, 1]` raises: a configuration typo should fail rather
than quietly turn the metric into always-pass.

A `minScore` makes the metric binary on purpose — some datasets want partial
credit for a softenable overstatement, and some want a line. Neither is right
for both.

## What it grades, precisely

The engine's checks read a row's **claims**, not its answer text. On its own
that would make this metric a constant per row — change the pipeline output,
leave the annotation alone, and the score would not move, which is useless in
an eval whose entire job is noticing when the output changes.

So the metric puts the answer back in the loop:

1. Each declared claim is reviewed **only if the produced answer actually
   asserted it**. A claim the pipeline never made is dropped, not failed — you
   are not on the hook for a claim you did not make.
2. A row whose answer asserted **none** of its declared claims scores **0.0**
   with `"reason": "The answer asserted none of the claims this row declared."`
   The pipeline answered something else entirely; reviewing the leftover
   annotation would grade the dataset and hand back a pass.

Matching is normalised containment — case-folded, whitespace-collapsed — of the
claim's own `text`, or of a `metadata.match` string (or list) when the row
declares one:

```yaml
claims:
  - id: c1
    text: 'Refunds are accepted for thirty days from delivery.'
    source_ids: [policy-page]
    metadata:
      match: ['30 days', 'thirty days']
```

It is crude on purpose. Anything cleverer means a model, and the point of this
metric is that it never calls one.

**What it still cannot do:** notice a claim the answer made that the row never
declared. Extracting claims from free text is a model's job. Pair it with a
judge for that half — which is the arrangement this page recommends anyway.

## No provider call, guaranteed

Every review runs with `cheap_only: true`, not merely `label_via_llm: false`.
The distinction matters: `labelViaLlm` only governs source-tier refinement, and
the engine still runs its **heavy** (LLM-backed) checks whenever
`evidence-risk-review.llm.enabled` is on and a cheap check produced a finding.

So a host that had turned the LLM integration on would have made this
"zero-token" metric bill a provider on exactly the rows that were already
failing. `cheapOnly` wins over the config flag, and a test asserts a bound LLM
is never invoked.

`ReviewOptions::$cheapOnly` is available to any caller that needs the same
guarantee — a health check, a request path with a latency budget.

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

**A malformed evidence block raises, it does not score zero.** *Absent* and
*malformed* are deliberately different outcomes: a row with no `evidence` key
has not been annotated yet, while a row whose block is present and wrong is a
broken dataset row, and treating it as un-annotated would let it into the
aggregate as a **pass**.

Raised, with the row named, and validated *before* the asserted-claims filter so
a broken row cannot be quietly dropped for failing to match the answer:

| Refused | Message |
|---|---|
| `evidence: 'oops'` | `metadata.evidence must be a map; got string.` |
| `claims: 'oops'` | `metadata.evidence.claims must be a list; got string.` |
| a claim without `id` or `text` | `every metadata.evidence.claims entry needs a non-empty string [id].` |
| `profile: 123` | `metadata.evidence.profile must be a non-empty string; got int.` |

A `minScore` outside `[0, 1]` — or `NAN` — is refused by the constructor, since
a typo there silently turns the metric into always-pass or always-fail and moves
an aggregate gate.

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
