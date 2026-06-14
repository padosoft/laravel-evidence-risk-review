<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Checks;

use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;

final class ContraindicationCheck extends KeywordRiskCheck
{
    public function kind(): RiskCheckKind
    {
        return RiskCheckKind::Contraindication;
    }
}
