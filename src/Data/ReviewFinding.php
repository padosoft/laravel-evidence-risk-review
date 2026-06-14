<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Data;

use Padosoft\EvidenceRiskReview\Enums\RiskCostClass;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;

final readonly class ReviewFinding
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public string $checkKind,
        public ?string $claimId,
        public RiskVerdict $verdict,
        public string $reason,
        public ?string $suggestedRewrite = null,
        public float $confidence = 0.0,
        public string $costClass = RiskCostClass::Cheap->value,
        public array $evidence = [],
    ) {}

    /**
     * @return array{
     *     check_kind: string,
     *     claim_id: string|null,
     *     verdict: string,
     *     reason: string,
     *     suggested_rewrite: string|null,
     *     confidence: float,
     *     cost_class: string,
     *     evidence: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'check_kind' => $this->checkKind,
            'claim_id' => $this->claimId,
            'verdict' => $this->verdict->value,
            'reason' => $this->reason,
            'suggested_rewrite' => $this->suggestedRewrite,
            'confidence' => $this->confidence,
            'cost_class' => $this->costClass,
            'evidence' => $this->evidence,
        ];
    }
}
