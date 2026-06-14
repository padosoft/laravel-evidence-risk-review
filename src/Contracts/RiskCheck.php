<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Contracts;

use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewFinding;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskCostClass;
use Padosoft\EvidenceRiskReview\Support\BudgetMeter;

interface RiskCheck
{
    public function kind(): RiskCheckKind;

    public function costClass(): RiskCostClass;

    public function supports(RiskProfileContract $profile): bool;

    /**
     * @return list<ReviewFinding>
     */
    public function run(ReviewArtifact $artifact, RiskProfileContract $profile, BudgetMeter $meter): array;
}
