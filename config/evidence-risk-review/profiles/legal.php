<?php

declare(strict_types=1);

use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;

return [
    'label' => 'Legal',
    'description' => 'Legal and compliance claims where jurisdiction and authority level must be explicit.',
    'enabled_checks' => [
        RiskCheckKind::EvidenceStrength->value,
        RiskCheckKind::OverGeneralization->value,
        RiskCheckKind::BoundaryCondition->value,
        RiskCheckKind::RedFlag->value,
    ],
    'min_tier' => [
        ClaimAssertiveness::Tentative->value => EvidenceTier::Official->value,
        ClaimAssertiveness::Likely->value => EvidenceTier::Official->value,
        ClaimAssertiveness::Definitive->value => EvidenceTier::Guideline->value,
    ],
    'checks' => [
        RiskCheckKind::EvidenceStrength->value => [
            'verdict' => RiskVerdict::FlagForHumanReview->value,
        ],
        RiskCheckKind::OverGeneralization->value => [
            'verdict' => RiskVerdict::Soften->value,
            'keywords' => ['always legal', 'never liable', 'guaranteed compliant', 'proves compliance'],
        ],
        RiskCheckKind::BoundaryCondition->value => [
            'verdict' => RiskVerdict::FlagForHumanReview->value,
            'keywords' => ['all jurisdictions', 'every court', 'without exception', 'regardless of law'],
        ],
        RiskCheckKind::RedFlag->value => [
            'verdict' => RiskVerdict::Remove->value,
            'keywords' => ['evade regulation', 'hide evidence', 'illegal workaround', 'circumvent law'],
        ],
    ],
];
