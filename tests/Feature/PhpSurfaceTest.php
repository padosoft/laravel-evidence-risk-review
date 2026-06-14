<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Feature;

use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\SourceRef;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\EvidenceRiskReview;
use Padosoft\EvidenceRiskReview\Facades\EvidenceRiskReview as EvidenceRiskReviewFacade;
use Padosoft\EvidenceRiskReview\Tests\TestCase;

final class PhpSurfaceTest extends TestCase
{
    public function test_service_reviews_array_payloads_and_exposes_taxonomy(): void
    {
        $surface = $this->resolve(EvidenceRiskReview::class);

        $result = $surface->reviewArray([
            'artifact_id' => 'surface-1',
            'answer_text' => 'This always cures the condition.',
            'claims' => [[
                'id' => 'c1',
                'text' => 'This always cures the condition.',
                'assertiveness' => 'definitive',
                'source_ids' => ['s1'],
            ]],
            'sources' => [[
                'id' => 's1',
                'declared_tier' => EvidenceTier::Blog->value,
            ]],
            'options' => [
                'dry_run' => true,
            ],
        ]);

        self::assertSame('surface-1', $result['artifact_id']);
        self::assertNotSame([], $result['findings']);
        self::assertArrayHasKey('default', $surface->listProfiles());
        self::assertArrayHasKey('tiers', $surface->taxonomy());
    }

    public function test_facade_delegates_to_the_php_surface(): void
    {
        $result = EvidenceRiskReviewFacade::review(new ReviewArtifact(
            artifactId: 'facade-1',
            answerText: 'No claims to check.',
        ));

        self::assertSame('facade-1', $result->artifactId);
        self::assertSame([], $result->findings);
    }

    public function test_label_tier_accepts_source_arrays_and_objects(): void
    {
        $surface = $this->resolve(EvidenceRiskReview::class);

        self::assertSame(
            EvidenceTier::Preprint->value,
            $surface->labelTier(['id' => 's1', 'url' => 'https://arxiv.org/abs/1234.5678'])->key,
        );

        self::assertSame(
            EvidenceTier::Guideline->value,
            $surface->labelTier(new SourceRef('s2', declaredTier: EvidenceTier::Guideline->value))->key,
        );
    }
}
