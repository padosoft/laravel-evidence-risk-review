<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit\Eval;

use Illuminate\Foundation\Application;
use Padosoft\EvalHarness\Datasets\DatasetSample;
use Padosoft\EvalHarness\Exceptions\MetricException;
use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Eval\EvidenceRiskMetric;
use Padosoft\EvidenceRiskReview\Log\ArrayReviewLogStore;
use Padosoft\EvidenceRiskReview\Tests\TestCase;
use RuntimeException;

/**
 * The review engine as an eval metric: deterministic groundedness scoring with
 * no provider call, so it can run on every row of every build rather than on a
 * sampled subset the way an LLM judge has to.
 */
final class EvidenceRiskMetricTest extends TestCase
{
    public function test_it_is_registered_under_a_stable_metric_name(): void
    {
        $this->assertSame('evidence-risk', $this->metric()->name());
    }

    public function test_a_well_sourced_answer_scores_one(): void
    {
        $score = $this->metric()->score(
            $this->sample([
                'claims' => [[
                    'id' => 'c1',
                    'text' => 'The latency improved in one benchmark.',
                    'assertiveness' => ClaimAssertiveness::Likely->value,
                    'source_ids' => ['official'],
                ]],
                'sources' => [['id' => 'official', 'declared_tier' => EvidenceTier::Official->value]],
            ]),
            'The latency improved in one benchmark.',
        );

        $this->assertSame(1.0, $score->score);
        $this->assertTrue($score->details['reviewed']);
        $this->assertSame('keep', $score->details['max_verdict']);
        $this->assertSame([], $score->details['findings']);
    }

    /**
     * A certain claim standing on a weak source is the failure this metric
     * exists to catch, and no model was asked anything to catch it.
     */
    public function test_an_overconfident_claim_on_a_weak_source_loses_points(): void
    {
        $score = $this->metric()->score(
            $this->sample([
                'claims' => [[
                    'id' => 'c1',
                    'text' => 'This always cures the condition.',
                    'assertiveness' => ClaimAssertiveness::Definitive->value,
                    'source_ids' => ['forum'],
                ]],
                'sources' => [['id' => 'forum', 'declared_tier' => EvidenceTier::Unverified->value]],
            ]),
            'This always cures the condition.',
        );

        $this->assertLessThan(1.0, $score->score);
        $this->assertNotSame([], $score->details['findings']);
        $this->assertGreaterThan(0.0, $score->details['risk_score']);

        // The reason is the line a briefing prints: a verdict labels, a reason
        // diagnoses.
        $this->assertNotSame('', $score->details['findings'][0]['reason']);
    }

    public function test_the_score_is_the_inverse_of_the_risk_score(): void
    {
        $score = $this->metric()->score(
            $this->sample([
                'claims' => [[
                    'id' => 'c1',
                    'text' => 'This always cures the condition.',
                    'assertiveness' => ClaimAssertiveness::Definitive->value,
                    'source_ids' => ['forum'],
                ]],
                'sources' => [['id' => 'forum', 'declared_tier' => EvidenceTier::Unverified->value]],
            ]),
            'This always cures the condition.',
        );

        $this->assertEqualsWithDelta(
            1.0 - $score->details['risk_score'],
            $score->details['groundedness'],
            1e-9,
        );
    }

    /**
     * Failing every row nobody has annotated yet would make the metric
     * impossible to adopt on an existing dataset.
     */
    public function test_a_row_with_no_evidence_block_scores_one_and_says_why(): void
    {
        $score = $this->metric()->score(
            new DatasetSample(id: 'plain', input: ['question' => 'q'], expectedOutput: 'a'),
            'an answer',
        );

        $this->assertSame(1.0, $score->score);
        $this->assertFalse($score->details['reviewed']);
        $this->assertStringContainsString('nothing is ungrounded', $score->details['reason']);
    }

    /**
     * An annotation somebody started and did not finish must not silently
     * become a verdict.
     */
    public function test_an_empty_evidence_block_is_treated_as_no_block(): void
    {
        $score = $this->metric()->score(
            $this->sample(['claims' => [], 'sources' => []]),
            'an answer',
        );

        $this->assertFalse($score->details['reviewed']);
    }

    /**
     * A CI job must not write thousands of synthetic rows into the log a
     * compliance team reads: that log records what production did.
     */
    public function test_reviews_run_during_an_eval_never_reach_the_audit_log(): void
    {
        $log = new ArrayReviewLogStore;
        $this->container()->instance(ReviewLogStore::class, $log);

        $this->metric()->score(
            $this->sample([
                'claims' => [[
                    'id' => 'c1',
                    'text' => 'This always cures the condition.',
                    'assertiveness' => ClaimAssertiveness::Definitive->value,
                    'source_ids' => ['forum'],
                ]],
                'sources' => [['id' => 'forum', 'declared_tier' => EvidenceTier::Unverified->value]],
            ]),
            'This always cures the condition.',
        );

        $this->assertSame([], $log->entries());
    }

    /**
     * Blaming the pipeline for the harness's own broken input would make every
     * annotation typo look like a groundedness failure.
     */
    public function test_a_malformed_evidence_block_raises_rather_than_scoring_zero(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage("Row 'broken' could not be reviewed");

        $this->metric()->score(
            new DatasetSample(
                id: 'broken',
                input: ['question' => 'q'],
                expectedOutput: 'a',
                metadata: ['evidence' => ['claims' => [['text' => 'missing its id']]]],
            ),
            'an answer',
        );
    }

    public function test_an_unknown_profile_raises_with_the_row_named(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage("Row 'row-1' could not be reviewed");

        $this->metric()->score(
            $this->sample([
                'profile' => 'no-such-profile',
                'claims' => [['id' => 'c1', 'text' => 'a claim', 'source_ids' => ['s1']]],
                'sources' => [['id' => 's1']],
            ]),
            'an answer',
        );
    }

    /**
     * Some datasets want partial credit for a softenable overstatement and
     * some want a line. A threshold makes the metric binary on purpose.
     */
    public function test_a_minimum_score_turns_the_metric_binary(): void
    {
        $evidence = [
            'claims' => [[
                'id' => 'c1',
                'text' => 'This always cures the condition.',
                'assertiveness' => ClaimAssertiveness::Definitive->value,
                'source_ids' => ['forum'],
            ]],
            'sources' => [['id' => 'forum', 'declared_tier' => EvidenceTier::Unverified->value]],
        ];

        $graded = $this->metric()->score($this->sample($evidence), 'This always cures the condition.');
        $strict = $this->metric(minScore: 1.0)->score($this->sample($evidence), 'This always cures the condition.');

        $this->assertGreaterThan(0.0, $graded->score);
        $this->assertSame(0.0, $strict->score);
        $this->assertSame(1.0, $strict->details['min_score']);
    }

    public function test_the_question_is_taken_from_the_row_input(): void
    {
        $score = $this->metric()->score(
            $this->sample([
                'claims' => [['id' => 'c1', 'text' => 'a claim', 'source_ids' => ['s1']]],
                'sources' => [['id' => 's1', 'declared_tier' => EvidenceTier::Official->value]],
            ]),
            'an answer',
        );

        // The artifact id ties the review back to the dataset row it came from.
        $this->assertTrue($score->details['reviewed']);
        $this->assertNotSame('', $score->details['review_id']);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function sample(array $evidence): DatasetSample
    {
        return new DatasetSample(
            id: 'row-1',
            input: ['question' => 'Does it help?'],
            expectedOutput: 'It helped in one benchmark.',
            metadata: ['evidence' => $evidence],
        );
    }

    private function metric(?float $minScore = null): EvidenceRiskMetric
    {
        return new EvidenceRiskMetric($this->container(), minScore: $minScore);
    }

    private function container(): Application
    {
        if ($this->app === null) {
            throw new RuntimeException('The Testbench application has not been booted.');
        }

        return $this->app;
    }
}
