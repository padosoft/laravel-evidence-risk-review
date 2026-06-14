<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Enums;

enum RiskCostClass: string
{
    case Cheap = 'cheap';
    case Heavy = 'heavy';
}
