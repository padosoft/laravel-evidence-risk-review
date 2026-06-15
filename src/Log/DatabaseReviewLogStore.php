<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Log;

use Illuminate\Database\ConnectionInterface;
use JsonException;
use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use Padosoft\EvidenceRiskReview\Contracts\TenantResolver;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Data\ReviewResult;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Tenancy\NullTenantResolver;

final readonly class DatabaseReviewLogStore implements ReviewLogStore
{
    private TenantResolver $tenants;

    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'evidence_risk_review_logs',
        ?TenantResolver $tenants = null,
    ) {
        $this->tenants = $tenants ?? new NullTenantResolver;
    }

    public function append(ReviewArtifact $artifact, ReviewOptions $options, ReviewResult $result): void
    {
        $payload = $result->toArray();

        $this->connection->table($this->table)->insert([
            'review_id' => $result->reviewId,
            'artifact_id' => $result->artifactId,
            'profile_key' => $result->profileKey,
            // In a multi-tenant host the resolver wins (the stored tenant can
            // never be spoofed by the client payload); standalone falls back to
            // whatever the artifact declared.
            'tenant_id' => $this->tenants->current() ?? $artifact->tenantId,
            'max_verdict' => RiskVerdict::highest($result->claimVerdicts)->value,
            'risk_score' => $result->riskScore,
            'findings' => $this->json($payload['findings']),
            'claim_verdicts' => $this->json($payload['claim_verdicts']),
            'source_tiers' => $this->json($payload['source_tiers']),
            'budget' => $this->json($payload['budget']),
            'artifact' => $this->json($artifact->toArray()),
            'options' => $this->json($options->toArray()),
            'metadata' => $this->json($payload['metadata']),
            'reviewed_at' => $result->reviewedAt->format('Y-m-d H:i:s'),
            'created_at' => $result->reviewedAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @throws JsonException
     */
    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
