<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Enums;

enum ClaimAssertiveness: string
{
    case Definitive = 'definitive';
    case Likely = 'likely';
    case Tentative = 'tentative';
}
