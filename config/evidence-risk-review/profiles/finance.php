<?php

declare(strict_types=1);

use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;

return [
    'label' => 'Finance',
    'description' => 'Financial claims with stronger checks for guarantees, suitability, and fraud-sensitive language.',
    'enabled_checks' => [
        RiskCheckKind::EvidenceStrength->value,
        RiskCheckKind::OverGeneralization->value,
        RiskCheckKind::SpecialPopulation->value,
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
            'verdict' => RiskVerdict::FlagForHumanReview->value,
            'keywords' => ['guaranteed return', 'risk-free', 'always profitable', 'cannot lose'],
        ],
        RiskCheckKind::SpecialPopulation->value => [
            'verdict' => RiskVerdict::FlagForHumanReview->value,
            'keywords' => ['retirees', 'minors', 'vulnerable customers', 'low income'],
        ],
        RiskCheckKind::BoundaryCondition->value => [
            'verdict' => RiskVerdict::Soften->value,
            'keywords' => ['any market', 'all investors', 'without risk', 'in every scenario'],
        ],
        RiskCheckKind::RedFlag->value => [
            'verdict' => RiskVerdict::Remove->value,
            'keywords' => ['insider information', 'market manipulation', 'launder', 'fraud'],
        ],
    ],
];
