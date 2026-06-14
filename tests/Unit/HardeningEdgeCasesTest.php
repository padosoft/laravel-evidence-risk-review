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
use Padosoft\EvidenceRiskReview\Data\LlmResponse;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Data\ReviewResult;
use Padosoft\EvidenceRiskReview\Data\SourceRef;
use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Exceptions\ProfileNotFoundException;
use Padosoft\EvidenceRiskReview\Llm\CallbackEvidenceReviewerLlm;
use Padosoft\EvidenceRiskReview\Llm\NullEvidenceReviewerLlm;
use Padosoft\EvidenceRiskReview\Log\NullReviewLogStore;
use Padosoft\EvidenceRiskReview\Profiles\DomainProfileRegistry;
use Padosoft\EvidenceRiskReview\Support\EvidenceTierLabeler;
use Padosoft\EvidenceRiskReview\Support\ReviewEngine;
use Padosoft\EvidenceRiskReview\Support\RiskSweepEngine;
use Padosoft\EvidenceRiskReview\Support\TierResolver;
use Padosoft\EvidenceRiskReview\Tests\TestCase;
use Padosoft\EvidenceRiskReview\ValueObjects\ReviewBudget;
use RuntimeException;

final class HardeningEdgeCasesTest extends TestCase
{
    public function test_empty_claims_review_is_clean(): void
    {
        $result = $this->engine()->review(new ReviewArtifact(
            artifactId: 'empty-claims',
            answerText: 'No claims are present.',
            sources: [new SourceRef('s1', declaredTier: 'official')],
        ), new ReviewOptions(dryRun: true));

        self::assertSame([], $result->findings);
        self::assertSame(0.0, $result->riskScore);
    }

    public function test_all_unverified_sources_flag_definitive_claims(): void
    {
        $result = $this->engine()->review(new ReviewArtifact(
            artifactId: 'unverified',
            answerText: 'This always works.',
            claims: [
                new ClaimRef('c1', 'This always works.', ClaimAssertiveness::Definitive, ['s1']),
            ],
            sources: [
                new SourceRef('s1'),
            ],
        ), new ReviewOptions(dryRun: true));

        self::assertSame(RiskVerdict::Soften, $result->claimVerdicts['c1']);
        self::assertSame('unverified', $result->findings[0]->evidence['best_tier'] ?? null);
    }

    public function test_exhausted_budget_skips_heavy_checks_without_calling_llm(): void
    {
        config()->set('evidence-risk-review.llm.enabled', true);
        $llmCalls = 0;
        $engine = $this->engine(new CallbackEvidenceReviewerLlm(function () use (&$llmCalls) {
            $llmCalls++;

            return new LlmResponse;
        }));

        $result = $engine->review(new ReviewArtifact(
            artifactId: 'budget-exhausted',
            answerText: 'This always works.',
            claims: [
                new ClaimRef('c1', 'This always works.', ClaimAssertiveness::Definitive, ['s1']),
            ],
            sources: [
                new SourceRef('s1'),
            ],
        ), new ReviewOptions(
            budget: new ReviewBudget(maxLlmCalls: 0, maxTokens: 0, maxHeavyChecks: 0, maxWallSeconds: 30),
            dryRun: true,
        ));

        self::assertSame(0, $llmCalls);
        self::assertSame('skipped_over_budget', $result->findings[1]->costClass);
    }

    public function test_unknown_custom_tier_in_hint_fails_loudly(): void
    {
        config()->set('evidence-risk-review.tier_hints', ['example.com' => 'ghost']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Evidence tier [ghost] is not configured.');

        $this->engine()->review(new ReviewArtifact(
            artifactId: 'unknown-tier',
            answerText: 'This likely works.',
            claims: [
                new ClaimRef('c1', 'This likely works.', ClaimAssertiveness::Likely, ['s1']),
            ],
            sources: [
                new SourceRef('s1', url: 'https://example.com/source'),
            ],
        ), new ReviewOptions(dryRun: true));
    }

    public function test_unknown_profile_fails_loudly(): void
    {
        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage('Risk profile [missing] is not registered.');

        $this->engine()->review(new ReviewArtifact('unknown-profile', 'Answer'), new ReviewOptions(profileKey: 'missing', dryRun: true));
    }

    public function test_append_failure_is_not_swallowed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('append failed');

        $this->engine(logStore: new class implements ReviewLogStore
        {
            public function append(ReviewArtifact $artifact, ReviewOptions $options, ReviewResult $result): void
            {
                throw new RuntimeException('append failed');
            }
        })->review(new ReviewArtifact('append-failure', 'Answer'));
    }

    private function engine(
        EvidenceReviewerLlmContract $llm = new NullEvidenceReviewerLlm,
        ?ReviewLogStore $logStore = null,
    ): ReviewEngine {
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
            $logStore ?? new NullReviewLogStore,
            $this->resolve(ConfigRepository::class),
        );
    }
}
