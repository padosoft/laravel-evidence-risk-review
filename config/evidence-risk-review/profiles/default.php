<?php

declare(strict_types=1);

use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;

return [
    'label' => 'Default',
    'description' => 'General purpose risk sweep for claims that need evidence-aware wording.',
    'enabled_checks' => [
        RiskCheckKind::EvidenceStrength->value,
        RiskCheckKind::OverGeneralization->value,
        RiskCheckKind::SpecialPopulation->value,
        RiskCheckKind::Contraindication->value,
        RiskCheckKind::BoundaryCondition->value,
        RiskCheckKind::RedFlag->value,
    ],
    'min_tier' => [
        ClaimAssertiveness::Tentative->value => EvidenceTier::SearchHint->value,
        ClaimAssertiveness::Likely->value => EvidenceTier::Official->value,
        ClaimAssertiveness::Definitive->value => EvidenceTier::PeerReviewed->value,
    ],
    'checks' => [
        RiskCheckKind::EvidenceStrength->value => [
            'verdict' => RiskVerdict::Soften->value,
        ],
        RiskCheckKind::OverGeneralization->value => [
            'verdict' => RiskVerdict::Soften->value,
            'keywords' => ['always', 'never', 'guaranteed', 'proves', 'eliminates'],
        ],
        RiskCheckKind::SpecialPopulation->value => [
            'verdict' => RiskVerdict::FlagForHumanReview->value,
            'keywords' => ['children', 'pregnant', 'elderly', 'immunocompromised'],
        ],
        RiskCheckKind::Contraindication->value => [
            'verdict' => RiskVerdict::FlagForHumanReview->value,
            'keywords' => ['contraindication', 'contraindicated', 'do not use', 'interaction'],
        ],
        RiskCheckKind::BoundaryCondition->value => [
            'verdict' => RiskVerdict::Soften->value,
            'keywords' => ['all cases', 'without exception', 'regardless of', 'any environment'],
        ],
        RiskCheckKind::RedFlag->value => [
            'verdict' => RiskVerdict::Remove->value,
            'keywords' => ['emergency', 'life-threatening', 'suicide', 'self-harm', 'fraud'],
        ],
    ],
];
