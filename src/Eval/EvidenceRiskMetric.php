<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Eval;

use Illuminate\Contracts\Container\Container;
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

    public function __construct(
        private readonly Container $container,
        private readonly string $profileKey = 'default',
        private readonly ?float $minScore = null,
    ) {}

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

        $result = $this->review($sample, $actualOutput, $evidence);
        $score = round(1.0 - $result->riskScore, 6);
        $threshold = $this->minScore;

        return new MetricScore(
            // A minScore turns the metric binary on purpose: some datasets want
            // partial credit for a softenable overstatement, and some want a
            // line. Neither is right for both.
            $threshold === null ? $score : ($score >= $threshold ? 1.0 : 0.0),
            [
                'reviewed' => true,
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
     */
    private function review(DatasetSample $sample, string $actualOutput, array $evidence): ReviewResult
    {
        $payload = [
            'artifact_id' => $sample->id,
            'answer_text' => $actualOutput,
            'question' => $this->questionFrom($sample),
            'claims' => $evidence['claims'] ?? [],
            'sources' => $evidence['sources'] ?? [],
            'metadata' => ['eval_dataset_row' => $sample->id],
        ];

        try {
            return $this->reviewer()->review(
                ReviewArtifact::fromArray($payload),
                new ReviewOptions(
                    profileKey: is_string($evidence['profile'] ?? null) && $evidence['profile'] !== ''
                        ? $evidence['profile']
                        : $this->profileKey,
                    labelViaLlm: false,
                    // Never written to the review log: that log records what
                    // production did, and a CI run is not production.
                    dryRun: true,
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
     * @return array<string, mixed>|null
     */
    private function evidenceFor(DatasetSample $sample): ?array
    {
        $evidence = $sample->metadata[self::EVIDENCE_KEY] ?? null;

        if (! is_array($evidence)) {
            return null;
        }

        $hasClaims = is_array($evidence['claims'] ?? null) && $evidence['claims'] !== [];
        $hasSources = is_array($evidence['sources'] ?? null) && $evidence['sources'] !== [];

        // An empty block is the same as no block: an annotation somebody
        // started and did not finish must not silently become a verdict.
        return $hasClaims || $hasSources ? $evidence : null;
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
