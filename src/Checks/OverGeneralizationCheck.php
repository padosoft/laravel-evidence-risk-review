<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Checks;

use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;

final class OverGeneralizationCheck extends KeywordRiskCheck
{
    public function kind(): RiskCheckKind
    {
        return RiskCheckKind::OverGeneralization;
    }
}
