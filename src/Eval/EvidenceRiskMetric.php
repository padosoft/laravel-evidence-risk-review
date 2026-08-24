<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Eval;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Padosoft\EvalHarness\Datasets\DatasetSample;
use Padosoft\EvalHarness\Exceptions\MetricException;
use Padosoft\EvalHarness\Metrics\Metric;
use Padosoft\EvalHarness\Metrics\MetricScore;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewFinding;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Data\ReviewResult;
use Padosoft\EvidenceRiskReview\EvidenceRiskReview;
use Padosoft\EvidenceRiskReview\ValueObjects\EvidenceTierValue;
use Throwable;

/**
 * Groundedness as an eval metric — deterministic, and free.
 *
 * ## What it grades, precisely
 *
 * The review engine's checks read a row's **claims**, not its answer text. On
 * its own that would make this metric a constant per row: change the pipeline
 * output, leave the annotation alone, and the score would not move — which is
 * useless in an eval, because the whole job is to notice when the output
 * changes.
 *
 * So the metric puts the answer back in the loop. Each declared claim is
 * reviewed **only if the produced answer actually asserted it** (see
 * {@see self::assertedClaims()}), and a row whose answer asserted none of its
 * declared claims scores **0.0** rather than passing on an annotation the
 * pipeline never lived up to. The score is therefore a function of the output,
 * and a regression in the pipeline moves it.
 *
 * What it still cannot do: notice a claim the answer made that the row never
 * declared. Extracting claims from free text is a model's job, and this metric
 * exists precisely to be the one that never calls a model. Pair it with a judge
 * for that half.
 *
 * ## What it is for
 *
 * `llm-as-judge` asks a model whether an answer is good. It is the most
 * capable metric in an eval suite and the most expensive one: a thousand rows
 * with three repetitions is three thousand paid calls that exist purely to
 * grade, and the grader is itself a model that can be wrong, is
 * non-deterministic, and disagrees with itself between runs.
 *
 * This review engine answers a narrower question — *is this answer actually
 * supported by the sources it cites, and does its confidence match its
 * evidence?* — with **deterministic checks and no provider call at all**. So it
 * costs nothing per row, returns the same score twice, and can run on every
 * row of every build rather than on a sampled subset.
 *
 * It is not a replacement for a judge. It is the metric you can afford to run
 * on all of it, next to the judge you run on some of it.
 *
 * ## The score
 *
 * `ReviewResult::$riskScore` runs 0 (nothing found) to 1 (something must be
 * removed), derived from the highest-severity finding. An eval metric scores
 * the other way round, so:
 *
 * ```
 * score = 1 - risk_score
 * ```
 *
 * With the harness's 0.5 pass threshold that puts the line between `soften`
 * (risk 0.33 → score 0.67, passes) and `flag_for_human_review` (risk 0.67 →
 * score 0.33, fails). In words: *a hedge-worthy overstatement is a note, an
 * answer that needs a human is a failure.* Move the line with `minScore` when
 * a dataset disagrees.
 *
 * ## Where the claims and sources come from
 *
 * The answer under test is the pipeline's output. Everything else — the claims
 * it made and the sources it cited — lives in the row's
 * `metadata.evidence` block, because only the dataset knows what the pipeline
 * was supposed to have grounded itself in:
 *
 * ```yaml
 * - id: refund-window
 *   input: { question: 'What is the refund window?' }
 *   expected_output: '30 days from delivery'
 *   metadata:
 *     evidence:
 *       profile: default
 *       claims:
 *         - { id: c1, text: 'Refunds are accepted for 30 days', assertiveness: definitive, source_ids: [s1] }
 *       sources:
 *         - { id: s1, url: 'https://example.test/policy', declared_tier: primary }
 * ```
 *
 * A row with no evidence block scores 1.0 and says so in its details: nothing
 * was claimed, so nothing is ungrounded. That is deliberate — the alternative,
 * failing every row that has not been annotated yet, makes the metric
 * impossible to adopt incrementally.
 *
 * ## Reviews from an eval run never reach the audit log
 *
 * Every review is run with `dry_run: true`, so a CI job does not write
 * thousands of synthetic rows into the review log a compliance team reads.
 * The log is a record of what production did; an eval is not production.
 */
final class EvidenceRiskMetric implements Metric
{
    public const NAME = 'evidence-risk';

    /** Metadata key holding this row's claims, sources and profile. */
    private const EVIDENCE_KEY = 'evidence';

    /**
     * @param  float|null  $minScore  turns the metric binary at this threshold
     *
     * @throws InvalidArgumentException when the threshold is outside [0, 1]
     */
    public function __construct(
        private readonly Container $container,
        private readonly string $profileKey = 'default',
        private readonly ?float $minScore = null,
    ) {
        // Validated here rather than at score time: a negative, greater-than-one
        // or non-finite threshold silently turns the metric into always-pass or
        // always-fail, so a configuration typo would move an aggregate gate
        // instead of failing.
        if ($minScore !== null && (! is_finite($minScore) || $minScore < 0.0 || $minScore > 1.0)) {
            throw new InvalidArgumentException(sprintf(
                'The evidence-risk minimum score must be a number between 0 and 1; got %s.',
                var_export($minScore, true),
            ));
        }
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function score(DatasetSample $sample, string $actualOutput): MetricScore
    {
        $evidence = $this->evidenceFor($sample);

        if ($evidence === null) {
            return new MetricScore(1.0, [
                'reviewed' => false,
                'reason' => 'No metadata.evidence block on this row: nothing was claimed, so nothing is ungrounded.',
            ]);
        }

        $declared = $this->claimsOf($evidence, $sample);
        // Resolved before the short circuit below: a row asking for a profile
        // that cannot be used is broken whether or not its answer matched.
        $this->profileFor($evidence, $sample);
        $asserted = $this->assertedClaims($declared, $actualOutput);

        // The pipeline answered something other than what this row is about.
        // Reviewing the leftover annotation would score the dataset rather than
        // the answer, and hand back a pass for an output that ignored it.
        if ($declared !== [] && $asserted === []) {
            return new MetricScore(0.0, [
                'reviewed' => true,
                'asserted_claims' => 0,
                'declared_claims' => count($declared),
                'reason' => 'The answer asserted none of the claims this row declared.',
            ]);
        }

        $result = $this->review($sample, $actualOutput, $evidence, $asserted);
        $score = round(1.0 - $result->riskScore, 6);
        $threshold = $this->minScore;

        return new MetricScore(
            // A minScore turns the metric binary on purpose: some datasets want
            // partial credit for a softenable overstatement, and some want a
            // line. Neither is right for both.
            $threshold === null ? $score : ($score >= $threshold ? 1.0 : 0.0),
            [
                'reviewed' => true,
                'asserted_claims' => count($asserted),
                'declared_claims' => count($declared),
                'review_id' => $result->reviewId,
                'profile_key' => $result->profileKey,
                'risk_score' => round($result->riskScore, 6),
                'groundedness' => $score,
                'min_score' => $threshold,
                'max_verdict' => $this->maxVerdict($result),
                'findings' => array_map(
                    static fn (ReviewFinding $finding): array => [
                        'check' => $finding->checkKind,
                        'claim_id' => $finding->claimId,
                        'verdict' => $finding->verdict->value,
                        // The line a briefing prints: a verdict is a label, the
                        // reason is the diagnosis.
                        'reason' => $finding->reason,
                        'suggested_rewrite' => $finding->suggestedRewrite,
                    ],
                    $result->findings,
                ),
                'source_tiers' => array_map(
                    static fn (EvidenceTierValue $tier): array => $tier->toArray(),
                    $result->sourceTiers,
                ),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  list<array<string, mixed>>  $claims  the declared claims the answer actually asserted
     */
    private function review(DatasetSample $sample, string $actualOutput, array $evidence, array $claims): ReviewResult
    {
        $payload = [
            'artifact_id' => $sample->id,
            'answer_text' => $actualOutput,
            'question' => $this->questionFrom($sample),
            'claims' => $claims,
            'sources' => $evidence['sources'] ?? [],
            'metadata' => ['eval_dataset_row' => $sample->id],
        ];

        try {
            return $this->reviewer()->review(
                ReviewArtifact::fromArray($payload),
                new ReviewOptions(
                    profileKey: $this->profileFor($evidence, $sample),
                    labelViaLlm: false,
                    // Never written to the review log: that log records what
                    // production did, and a CI run is not production.
                    dryRun: true,
                    // The guarantee this metric is sold on. `labelViaLlm: false`
                    // alone would not give it: the engine still runs its heavy
                    // checks whenever the host has the LLM integration enabled.
                    cheapOnly: true,
                ),
            );
        } catch (Throwable $e) {
            // Raised rather than scored 0.0: a malformed evidence block is a
            // broken dataset row, and scoring it as "ungrounded" would blame
            // the pipeline for the harness's input.
            throw new MetricException(sprintf(
                "Row '%s' could not be reviewed: %s",
                $sample->id,
                $e->getMessage(),
            ), previous: $e);
        }
    }

    /**
     * The row's evidence block, or null when the row has none.
     *
     * Absent and malformed are deliberately different outcomes. A row with no
     * `metadata.evidence` key has not been annotated yet and scores 1.0 so the
     * metric can be adopted on an existing dataset one row at a time. A row
     * whose block is *present and wrong* — a string instead of a map, `claims`
     * that is not a list — is a broken dataset row, and treating it as
     * un-annotated would let it into the aggregate as a pass.
     *
     * @return array<string, mixed>|null
     *
     * @throws MetricException when the block is present but malformed
     */
    private function evidenceFor(DatasetSample $sample): ?array
    {
        if (! array_key_exists(self::EVIDENCE_KEY, $sample->metadata)) {
            return null;
        }

        $evidence = $sample->metadata[self::EVIDENCE_KEY];

        if ($evidence === null) {
            return null;
        }

        if (! is_array($evidence)) {
            throw $this->malformed($sample, sprintf(
                'metadata.evidence must be a map; got %s.',
                get_debug_type($evidence),
            ));
        }

        foreach (['claims', 'sources'] as $key) {
            if (array_key_exists($key, $evidence) && ! is_array($evidence[$key])) {
                throw $this->malformed($sample, sprintf(
                    'metadata.evidence.%s must be a list; got %s.',
                    $key,
                    get_debug_type($evidence[$key]),
                ));
            }
        }

        $hasClaims = is_array($evidence['claims'] ?? null) && $evidence['claims'] !== [];
        $hasSources = is_array($evidence['sources'] ?? null) && $evidence['sources'] !== [];

        // An empty block is the same as no block: an annotation somebody
        // started and did not finish must not silently become a verdict.
        return $hasClaims || $hasSources ? $evidence : null;
    }

    /**
     * The declared claims, as maps.
     *
     * @param  array<string, mixed>  $evidence
     * @return list<array<string, mixed>>
     */
    private function claimsOf(array $evidence, DatasetSample $sample): array
    {
        $claims = [];

        foreach ($evidence['claims'] ?? [] as $claim) {
            if (! is_array($claim)) {
                throw $this->malformed($sample, sprintf(
                    'every metadata.evidence.claims entry must be a map; got %s.',
                    get_debug_type($claim),
                ));
            }

            // Validated here rather than left to the engine, because the
            // asserted-claims filter runs first: a claim missing its id would
            // otherwise be silently dropped for not matching the answer, and
            // the broken row would score instead of raising.
            foreach (['id', 'text'] as $required) {
                if (! is_string($claim[$required] ?? null) || $claim[$required] === '') {
                    throw $this->malformed($sample, sprintf(
                        'every metadata.evidence.claims entry needs a non-empty string [%s].',
                        $required,
                    ));
                }
            }

            /** @var array<string, mixed> $claim */
            $claims[] = $claim;
        }

        return $claims;
    }

    /**
     * The declared claims the produced answer actually asserted.
     *
     * Matching is normalised containment — case-folded, whitespace-collapsed —
     * of the claim's `metadata.match` when it declares one, else of the claim's
     * own text. Crude on purpose: anything cleverer means a model, and the
     * point of this metric is that it never calls one. A row that needs a
     * looser match says so with `match`.
     *
     * A claim the answer did not make is dropped rather than failed: the
     * pipeline is not on the hook for a claim it never asserted.
     *
     * @param  list<array<string, mixed>>  $claims
     * @return list<array<string, mixed>>
     */
    private function assertedClaims(array $claims, string $actualOutput): array
    {
        $answer = $this->normalise($actualOutput);

        return array_values(array_filter($claims, function (array $claim) use ($answer): bool {
            foreach ($this->matchNeedles($claim) as $needle) {
                if ($needle !== '' && str_contains($answer, $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  array<string, mixed>  $claim
     * @return list<string>
     */
    private function matchNeedles(array $claim): array
    {
        $metadata = $claim['metadata'] ?? null;
        $match = is_array($metadata) ? ($metadata['match'] ?? null) : null;

        if (is_string($match)) {
            return [$this->normalise($match)];
        }

        if (is_array($match)) {
            $needles = [];

            foreach ($match as $candidate) {
                if (is_string($candidate)) {
                    $needles[] = $this->normalise($candidate);
                }
            }

            if ($needles !== []) {
                return $needles;
            }
        }

        return is_string($claim['text'] ?? null) ? [$this->normalise($claim['text'])] : [];
    }

    private function normalise(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($value)));
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function profileFor(array $evidence, DatasetSample $sample): string
    {
        if (! array_key_exists('profile', $evidence) || $evidence['profile'] === null) {
            return $this->profileKey;
        }

        $profile = $evidence['profile'];

        // A profile that is present and unusable must not fall back: the row
        // would silently be judged by a different policy than it asked for.
        if (! is_string($profile) || $profile === '') {
            throw $this->malformed($sample, sprintf(
                'metadata.evidence.profile must be a non-empty string; got %s.',
                get_debug_type($profile),
            ));
        }

        return $profile;
    }

    private function malformed(DatasetSample $sample, string $detail): MetricException
    {
        return new MetricException(sprintf("Row '%s' could not be reviewed: %s", $sample->id, $detail));
    }

    private function questionFrom(DatasetSample $sample): ?string
    {
        foreach (['question', 'query', 'prompt', 'input'] as $key) {
            $value = $sample->input[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function maxVerdict(ReviewResult $result): string
    {
        $max = 'keep';
        $severity = -1;

        foreach ($result->findings as $finding) {
            if ($finding->verdict->severity() > $severity) {
                $severity = $finding->verdict->severity();
                $max = $finding->verdict->value;
            }
        }

        return $max;
    }

    private function reviewer(): EvidenceRiskReview
    {
        /** @var EvidenceRiskReview $reviewer */
        $reviewer = $this->container->make(EvidenceRiskReview::class);

        return $reviewer;
    }
}
