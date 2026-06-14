<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Data\ClaimRef;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DtoRoundTripTest extends TestCase
{
    #[Test]
    public function review_artifact_round_trips_through_arrays(): void
    {
        $payload = [
            'artifact_id' => 'artifact-1',
            'answer_text' => 'A careful answer with one cited claim.',
            'question' => 'What changed?',
            'tenant_id' => 'tenant-a',
            'claims' => [
                [
                    'id' => 'claim-1',
                    'text' => 'The evidence is likely enough for a cautious statement.',
                    'assertiveness' => 'likely',
                    'source_ids' => ['source-1'],
                    'metadata' => ['span' => '0:12'],
                ],
            ],
            'sources' => [
                [
                    'id' => 'source-1',
                    'url' => 'https://example.test/article',
                    'title' => 'Example evidence',
                    'snippet' => 'doi: 10.1234/example',
                    'declared_tier' => 'peer_reviewed',
                    'metadata' => ['doi' => '10.1234/example'],
                ],
            ],
            'metadata' => ['trace_id' => 'trace-1'],
        ];

        self::assertSame($payload, ReviewArtifact::fromArray($payload)->toArray());
    }

    #[Test]
    public function claim_source_ids_must_be_a_json_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ClaimRef::fromArray([
            'id' => 'claim-1',
            'text' => 'A claim.',
            'source_ids' => ['primary' => 'source-1'],
        ]);
    }

    #[Test]
    public function review_artifact_claims_must_be_a_json_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ReviewArtifact::fromArray([
            'artifact_id' => 'artifact-1',
            'answer_text' => 'A careful answer.',
            'claims' => [
                'claim-1' => [
                    'id' => 'claim-1',
                    'text' => 'A claim.',
                ],
            ],
        ]);
    }

    #[Test]
    public function review_artifact_sources_must_be_a_json_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ReviewArtifact::fromArray([
            'artifact_id' => 'artifact-1',
            'answer_text' => 'A careful answer.',
            'sources' => [
                'source-1' => [
                    'id' => 'source-1',
                ],
            ],
        ]);
    }
}
