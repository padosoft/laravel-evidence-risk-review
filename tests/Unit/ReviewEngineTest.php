<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Checks\EvidenceStrengthCheck;
use Padosoft\EvidenceRiskReview\Checks\LlmEvidenceStrengthCheck;
use Padosoft\EvidenceRiskReview\Contracts\EvidenceReviewerLlmContract;
use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use Padosoft\EvidenceRiskReview\Data\ClaimRef;
use Padosoft\EvidenceRiskReview\Data\LlmRequest;
use Padosoft\EvidenceRiskReview\Data\LlmResponse;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Data\SourceRef;
use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Llm\CallbackEvidenceReviewerLlm;
use Padosoft\EvidenceRiskReview\Log\ArrayReviewLogStore;
use Padosoft\EvidenceRiskReview\Profiles\DomainProfileRegistry;
use Padosoft\EvidenceRiskReview\Support\EvidenceTierLabeler;
use Padosoft\EvidenceRiskReview\Support\ReviewEngine;
use Padosoft\EvidenceRiskReview\Support\RiskSweepEngine;
use Padosoft\EvidenceRiskReview\Support\TierResolver;
use Padosoft\EvidenceRiskReview\Tests\TestCase;
use Padosoft\EvidenceRiskReview\ValueObjects\ReviewBudget;

final class ReviewEngineTest extends TestCase
{
    public function test_review_engine_short_circuits_heavy_checks_when_cheap_pass_clears(): void
    {
        config()->set('evidence-risk-review.llm.enabled', true);

        $llmCalls = 0;
        $log = new ArrayReviewLogStore;
        $engine = $this->engine(new CallbackEvidenceReviewerLlm(function () use (&$llmCalls): LlmResponse {
            $llmCalls++;

            return new LlmResponse;
        }), $log);

        $result = $engine->review(new ReviewArtifact(
            artifactId: 'artifact-1',
            answerText: 'The latency improved in one benchmark.',
            claims: [
                new ClaimRef('c1', 'The latency improved in one benchmark.', ClaimAssertiveness::Likely, ['official']),
            ],
            sources: [
                new SourceRef('official', declaredTier: EvidenceTier::Official->value),
            ],
        ));

        self::assertSame(0, $llmCalls);
        self::assertSame(0, $result->budget->llmCalls);
        self::assertSame([], $result->findings);
        self::assertSame(1, count($log->entries()));
    }

    public function test_review_engine_runs_heavy_check_when_cheap_findings_exist(): void
    {
        config()->set('evidence-risk-review.llm.enabled', true);

        $engine = $this->engine(new CallbackEvidenceReviewerLlm(static fn (LlmRequest $request): LlmResponse => new LlmResponse(
            data: [
                'findings' => [[
                    'claim_id' => 'c1',
                    'verdict' => RiskVerdict::FlagForHumanReview->value,
                    'reason' => 'LLM found missing context.',
                    'confidence' => 0.9,
                ]],
            ],
            tokensUsed: 17,
        )));

        $result = $engine->review(new ReviewArtifact(
            artifactId: 'artifact-2',
            answerText: 'This likely works.',
            claims: [
                new ClaimRef('c1', 'This likely works.', ClaimAssertiveness::Likely, ['blog']),
            ],
            sources: [
                new SourceRef('blog', declaredTier: EvidenceTier::Blog->value),
            ],
        ), new ReviewOptions(budget: new ReviewBudget(maxLlmCalls: 2, maxTokens: 100, maxHeavyChecks: 2)));

        self::assertSame(1, $result->budget->llmCalls);
        self::assertSame(17, $result->budget->tokens);
        self::assertSame(RiskVerdict::FlagForHumanReview, $result->claimVerdicts['c1']);
        self::assertTrue($result->metadata['heavy_checks_run']);
    }

    public function test_review_engine_honors_default_off_llm_flag_before_heavy_checks(): void
    {
        config()->set('evidence-risk-review.llm.enabled', false);

        $llmCalls = 0;
        $engine = $this->engine(new CallbackEvidenceReviewerLlm(function () use (&$llmCalls): LlmResponse {
            $llmCalls++;

            return new LlmResponse;
        }));

        $result = $engine->review(new ReviewArtifact(
            artifactId: 'artifact-off',
            answerText: 'This likely works.',
            claims: [
                new ClaimRef('c1', 'This likely works.', ClaimAssertiveness::Likely, ['blog']),
            ],
            sources: [
                new SourceRef('blog', declaredTier: EvidenceTier::Blog->value),
            ],
        ));

        self::assertSame(0, $llmCalls);
        self::assertSame(0, $result->budget->llmCalls);
        self::assertFalse($result->metadata['heavy_checks_run']);
        self::assertFalse($result->metadata['llm_enabled']);
    }

    public function test_review_engine_applies_llm_source_tier_refinements_when_enabled(): void
    {
        config()->set('evidence-risk-review.llm.enabled', true);

        $engine = $this->engine(new CallbackEvidenceReviewerLlm(static fn (): LlmResponse => new LlmResponse(
            data: ['source_tiers' => ['s1' => EvidenceTier::PeerReviewed->value]],
            tokensUsed: 11,
        )));

        $result = $engine->review(new ReviewArtifact(
            artifactId: 'artifact-tier',
            answerText: 'Answer',
            sources: [
                new SourceRef('s1', declaredTier: EvidenceTier::Blog->value),
            ],
        ), new ReviewOptions(budget: new ReviewBudget(maxLlmCalls: 1, maxTokens: 100, maxHeavyChecks: 1), labelViaLlm: true));

        self::assertSame(EvidenceTier::PeerReviewed->value, $result->sourceTiers['s1']->key);
        self::assertSame(1, $result->budget->llmCalls);
        self::assertSame(11, $result->budget->tokens);
    }

    public function test_review_engine_respects_dry_run_without_logging(): void
    {
        $log = new ArrayReviewLogStore;
        $engine = $this->engine(new CallbackEvidenceReviewerLlm(static fn (): LlmResponse => new LlmResponse), $log);

        $engine->review(new ReviewArtifact('artifact-3', 'Answer'), new ReviewOptions(dryRun: true));

        self::assertSame([], $log->entries());
    }

    public function test_unknown_configured_log_store_fails_loudly(): void
    {
        config()->set('evidence-risk-review.review_log.store', 'databse');
        $this->app?->forgetInstance(ReviewLogStore::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown evidence risk review log store [databse].');

        $this->resolve(ReviewLogStore::class);
    }

    private function engine(EvidenceReviewerLlmContract $llm, ?ArrayReviewLogStore $log = null): ReviewEngine
    {
        $tiers = $this->resolve(TierResolver::class);
        $labeler = $this->resolve(EvidenceTierLabeler::class);

        return new ReviewEngine(
            $this->resolve(DomainProfileRegistry::class),
            new RiskSweepEngine([
                new EvidenceStrengthCheck($labeler, $tiers),
                new LlmEvidenceStrengthCheck($llm),
            ]),
            $labeler,
            $tiers,
            $llm,
            $log ?? new ArrayReviewLogStore,
            $this->resolve(ConfigRepository::class),
        );
    }
}
