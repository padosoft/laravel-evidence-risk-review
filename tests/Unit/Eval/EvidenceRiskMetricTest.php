<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit\Eval;

use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Padosoft\EvalHarness\Datasets\DatasetSample;
use Padosoft\EvalHarness\Exceptions\MetricException;
use Padosoft\EvidenceRiskReview\Checks\EvidenceStrengthCheck;
use Padosoft\EvidenceRiskReview\Contracts\EvidenceReviewerLlmContract;
use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use Padosoft\EvidenceRiskReview\Contracts\RiskCheck;
use Padosoft\EvidenceRiskReview\Contracts\RiskProfileContract;
use Padosoft\EvidenceRiskReview\Data\LlmResponse;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewFinding;
use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskCostClass;
use Padosoft\EvidenceRiskReview\Eval\EvidenceRiskMetric;
use Padosoft\EvidenceRiskReview\Llm\CallbackEvidenceReviewerLlm;
use Padosoft\EvidenceRiskReview\Log\ArrayReviewLogStore;
use Padosoft\EvidenceRiskReview\Support\BudgetMeter;
use Padosoft\EvidenceRiskReview\Support\RiskSweepEngine;
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
            'the answer repeats a claim',
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

    /**
     * The question reaches the engine, observed at the check boundary rather
     * than inferred from a passing score: a test that only asserts `reviewed`
     * would still pass if the metric never sent a question at all.
     */
    public function test_the_question_and_the_answer_reach_the_review(): void
    {
        $recorder = $this->recordArtifacts();

        $this->metric()->score(
            $this->sample([
                'claims' => [['id' => 'c1', 'text' => 'it helps', 'source_ids' => ['s1']]],
                'sources' => [['id' => 's1', 'declared_tier' => EvidenceTier::Official->value]],
            ]),
            'In one benchmark it helps.',
        );

        $artifact = $recorder->last();

        $this->assertNotNull($artifact);
        $this->assertSame('Does it help?', $artifact->question);
        $this->assertSame('In one benchmark it helps.', $artifact->answerText);
        $this->assertSame('row-1', $artifact->artifactId);
    }

    /**
     * The guarantee the metric is sold on. `labelViaLlm: false` alone does NOT
     * give it: the engine runs its heavy checks whenever the host has the LLM
     * integration enabled, so a host that turned it on would have made this
     * "zero-token" metric bill a provider on every failing row.
     */
    public function test_no_provider_is_called_even_when_the_host_enabled_the_llm(): void
    {
        config()->set('evidence-risk-review.llm.enabled', true);

        $calls = 0;
        $this->container()->instance(
            EvidenceReviewerLlmContract::class,
            new CallbackEvidenceReviewerLlm(function () use (&$calls): LlmResponse {
                $calls++;

                return new LlmResponse;
            }),
        );

        // A finding-producing row: without cheapOnly this is exactly the shape
        // that triggers the heavy sweep.
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

        $this->assertSame(0, $calls, 'the metric must never reach a provider');
        $this->assertLessThan(1.0, $score->score);
    }

    /**
     * Without this the metric would be a constant per row: the engine's checks
     * read the claims, not the answer, so changing the pipeline output while
     * leaving the annotation alone would not move the score — useless in an
     * eval, whose entire job is noticing when the output changes.
     */
    public function test_the_score_moves_when_the_pipeline_output_changes(): void
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

        $overclaiming = $this->metric()->score($this->sample($evidence), 'This always cures the condition.');
        $hedged = $this->metric()->score($this->sample($evidence), 'One forum thread reports an improvement.');

        $this->assertLessThan(1.0, $overclaiming->score);
        $this->assertNotSame($overclaiming->score, $hedged->score);
    }

    /**
     * The pipeline answered something else entirely. Reviewing the leftover
     * annotation would grade the dataset and hand back a pass.
     */
    public function test_an_answer_that_asserts_none_of_the_declared_claims_scores_zero(): void
    {
        $score = $this->metric()->score(
            $this->sample([
                'claims' => [['id' => 'c1', 'text' => 'the refund window is 30 days', 'source_ids' => ['s1']]],
                'sources' => [['id' => 's1', 'declared_tier' => EvidenceTier::Official->value]],
            ]),
            'I am sorry, I cannot help with that.',
        );

        $this->assertSame(0.0, $score->score);
        $this->assertSame(0, $score->details['asserted_claims']);
        $this->assertStringContainsString('asserted none of the claims', $score->details['reason']);
    }

    public function test_a_claim_can_declare_a_looser_match_string(): void
    {
        $score = $this->metric()->score(
            $this->sample([
                'claims' => [[
                    'id' => 'c1',
                    'text' => 'Refunds are accepted for thirty days from delivery.',
                    'source_ids' => ['s1'],
                    'metadata' => ['match' => ['30 days', 'thirty days']],
                ]],
                'sources' => [['id' => 's1', 'declared_tier' => EvidenceTier::Official->value]],
            ]),
            'You have 30 days from delivery.',
        );

        $this->assertSame(1, $score->details['asserted_claims']);
    }

    public function test_matching_ignores_case_and_whitespace(): void
    {
        $score = $this->metric()->score(
            $this->sample([
                'claims' => [['id' => 'c1', 'text' => 'It   helps', 'source_ids' => ['s1']]],
                'sources' => [['id' => 's1', 'declared_tier' => EvidenceTier::Official->value]],
            ]),
            'In one benchmark IT HELPS a little.',
        );

        $this->assertSame(1, $score->details['asserted_claims']);
    }

    /**
     * Absent and malformed are different outcomes: an un-annotated row lets the
     * metric be adopted a row at a time, a broken block must not enter the
     * aggregate as a pass.
     */
    public function test_an_evidence_block_that_is_not_a_map_raises(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage('metadata.evidence must be a map');

        $this->metric()->score(
            new DatasetSample(id: 'row-1', input: [], expectedOutput: 'a', metadata: ['evidence' => 'oops']),
            'an answer',
        );
    }

    public function test_claims_that_are_not_a_list_raise(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage('metadata.evidence.claims must be a list');

        $this->metric()->score($this->sample(['claims' => 'oops', 'sources' => [['id' => 's1']]]), 'an answer');
    }

    public function test_a_claim_without_an_id_raises_even_when_the_answer_does_not_match_it(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage('metadata.evidence.claims[0] is invalid');

        $this->metric()->score(
            $this->sample(['claims' => [['text' => 'missing its id']], 'sources' => [['id' => 's1']]]),
            'something else entirely',
        );
    }

    /**
     * A profile that is present and unusable must not fall back: the row would
     * be judged by a different policy than it asked for.
     */
    public function test_a_non_string_profile_raises(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage('metadata.evidence.profile must be a non-empty string');

        $this->metric()->score(
            $this->sample([
                'profile' => 123,
                'claims' => [['id' => 'c1', 'text' => 'a claim', 'source_ids' => ['s1']]],
                'sources' => [['id' => 's1']],
            ]),
            'an answer',
        );
    }

    /**
     * A configuration typo in a threshold should fail, not silently turn the
     * metric into always-pass and move an aggregate gate.
     */
    public function test_a_threshold_outside_the_unit_interval_is_refused(): void
    {
        foreach ([-0.1, 1.1, NAN, INF] as $invalid) {
            try {
                new EvidenceRiskMetric($this->container(), minScore: $invalid);
                $this->fail(sprintf('A minimum score of %s should have been refused.', var_export($invalid, true)));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('between 0 and 1', $e->getMessage());
            }
        }
    }

    /**
     * A non-empty list is an array, so it slipped past the map check and
     * reached the empty-block short circuit — a present, malformed block
     * reported as "not annotated" and scored 1.0.
     */
    public function test_a_list_shaped_evidence_block_raises(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage('metadata.evidence must be a map, not a list');

        $this->metric()->score(
            new DatasetSample(id: 'row-1', input: [], expectedOutput: 'a', metadata: ['evidence' => ['unexpected']]),
            'an answer',
        );
    }

    /**
     * Validating only id and text let a claim with a broken `assertiveness` be
     * silently dropped by the answer-match filter and the row scored, instead
     * of raising against the annotation.
     */
    public function test_a_claim_with_an_invalid_assertiveness_raises_even_when_unmatched(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage('metadata.evidence.claims[0] is invalid');

        $this->metric()->score(
            $this->sample([
                'claims' => [['id' => 'c1', 'text' => 'a claim', 'assertiveness' => 'wildly-sure', 'source_ids' => ['s1']]],
                'sources' => [['id' => 's1']],
            ]),
            'something else entirely',
        );
    }

    public function test_a_malformed_source_raises(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage('metadata.evidence.sources[0] is invalid');

        $this->metric()->score(
            $this->sample([
                'claims' => [['id' => 'c1', 'text' => 'a claim', 'source_ids' => ['s1']]],
                'sources' => [['url' => 'https://example.test/no-id']],
            ]),
            'a claim',
        );
    }

    /**
     * The profile was type-checked but never resolved, so an unknown key on a
     * row whose answer matched nothing hit the short circuit and scored 0.0
     * instead of raising.
     */
    public function test_an_unknown_profile_raises_even_when_the_answer_matches_nothing(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage('profile [no-such-profile] could not be resolved');

        $this->metric()->score(
            $this->sample([
                'profile' => 'no-such-profile',
                'claims' => [['id' => 'c1', 'text' => 'a claim', 'source_ids' => ['s1']]],
                'sources' => [['id' => 's1']],
            ]),
            'something else entirely',
        );
    }

    /**
     * An explicit null is a value, not an omission: the key is present, so
     * falling back would judge the row by a policy it did not ask for.
     */
    public function test_an_explicit_null_profile_raises_rather_than_falling_back(): void
    {
        $this->expectException(MetricException::class);
        $this->expectExceptionMessage('metadata.evidence.profile must be a non-empty string');

        $this->metric()->score(
            $this->sample([
                'profile' => null,
                'claims' => [['id' => 'c1', 'text' => 'a claim', 'source_ids' => ['s1']]],
                'sources' => [['id' => 's1']],
            ]),
            'a claim',
        );
    }

    /**
     * `cheap_only` reaches the HTTP surface through ReviewOptions::fromArray,
     * so the wire contract has to declare it or the no-provider guarantee is
     * undiscoverable to an API consumer.
     */
    public function test_the_openapi_schema_declares_the_cheap_only_option(): void
    {
        $schema = (string) file_get_contents(__DIR__.'/../../../resources/openapi.yaml');

        $this->assertStringContainsString('cheap_only:', $schema);
    }

    private function recordArtifacts(): ArtifactRecorder
    {
        $recorder = new ArtifactRecorder;

        // Injected as an extra check so the artifact is observed exactly where
        // the engine hands it to one, without stubbing the engine itself.
        $this->container()->instance(
            RiskSweepEngine::class,
            new RiskSweepEngine([
                $recorder,
                $this->resolve(EvidenceStrengthCheck::class),
            ]),
        );

        return $recorder;
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

/**
 * A no-op check that keeps the artifact it was handed, so a test can assert on
 * what actually reached the engine rather than on a score that would look the
 * same either way.
 */
final class ArtifactRecorder implements RiskCheck
{
    private ?ReviewArtifact $artifact = null;

    public function kind(): RiskCheckKind
    {
        return RiskCheckKind::RedFlag;
    }

    public function costClass(): RiskCostClass
    {
        return RiskCostClass::Cheap;
    }

    public function supports(RiskProfileContract $profile): bool
    {
        return true;
    }

    /**
     * @return list<ReviewFinding>
     */
    public function run(ReviewArtifact $artifact, RiskProfileContract $profile, BudgetMeter $meter): array
    {
        $this->artifact = $artifact;

        return [];
    }

    public function last(): ?ReviewArtifact
    {
        return $this->artifact;
    }
}
