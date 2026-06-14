<?php

declare(strict_types=1);

use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;

return [
    'label' => 'Medical',
    'description' => 'Health-related claims with stricter evidence thresholds and patient safety guardrails.',
    'enabled_checks' => [
        RiskCheckKind::EvidenceStrength->value,
        RiskCheckKind::OverGeneralization->value,
        RiskCheckKind::SpecialPopulation->value,
        RiskCheckKind::Contraindication->value,
        RiskCheckKind::BoundaryCondition->value,
        RiskCheckKind::RedFlag->value,
    ],
    'min_tier' => [
        ClaimAssertiveness::Tentative->value => EvidenceTier::Official->value,
        ClaimAssertiveness::Likely->value => EvidenceTier::PeerReviewed->value,
        ClaimAssertiveness::Definitive->value => EvidenceTier::Guideline->value,
    ],
    'checks' => [
        RiskCheckKind::EvidenceStrength->value => [
            'verdict' => RiskVerdict::FlagForHumanReview->value,
        ],
        RiskCheckKind::OverGeneralization->value => [
            'verdict' => RiskVerdict::Soften->value,
            'keywords' => ['always', 'never', 'cures', 'prevents', 'safe for everyone'],
        ],
        RiskCheckKind::SpecialPopulation->value => [
            'verdict' => RiskVerdict::FlagForHumanReview->value,
            'keywords' => ['children', 'pregnant', 'elderly', 'renal impairment', 'immunocompromised'],
        ],
        RiskCheckKind::Contraindication->value => [
            'verdict' => RiskVerdict::Remove->value,
            'keywords' => ['contraindication', 'contraindicated', 'do not use', 'adverse reaction', 'drug interaction'],
        ],
        RiskCheckKind::BoundaryCondition->value => [
            'verdict' => RiskVerdict::FlagForHumanReview->value,
            'keywords' => ['all patients', 'without monitoring', 'any dose', 'no side effects'],
        ],
        RiskCheckKind::RedFlag->value => [
            'verdict' => RiskVerdict::Remove->value,
            'keywords' => ['emergency', 'life-threatening', 'suicide', 'self-harm', 'overdose'],
        ],
    ],
];
