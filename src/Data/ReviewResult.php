<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Data;

use DateTimeImmutable;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\ValueObjects\EvidenceTierValue;

final readonly class ReviewResult
{
    /**
     * @param  list<ReviewFinding>  $findings
     * @param  array<string, RiskVerdict>  $claimVerdicts
     * @param  array<string, EvidenceTierValue>  $sourceTiers
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $reviewId,
        public string $artifactId,
        public string $profileKey,
        public array $findings,
        public array $claimVerdicts,
        public array $sourceTiers,
        public float $riskScore,
        public BudgetConsumption $budget,
        public DateTimeImmutable $reviewedAt,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'review_id' => $this->reviewId,
            'artifact_id' => $this->artifactId,
            'profile_key' => $this->profileKey,
            'findings' => array_map(
                static fn (ReviewFinding $finding): array => $finding->toArray(),
                $this->findings,
            ),
            'claim_verdicts' => array_map(
                static fn (RiskVerdict $verdict): string => $verdict->value,
                $this->claimVerdicts,
            ),
            'source_tiers' => array_map(
                static fn (EvidenceTierValue $tier): array => $tier->toArray(),
                $this->sourceTiers,
            ),
            'risk_score' => $this->riskScore,
            'budget' => $this->budget->toArray(),
            'reviewed_at' => $this->reviewedAt->format(DATE_ATOM),
            'metadata' => $this->metadata,
        ];
    }
}
