<?php

declare(strict_types=1);

use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;

return [
    'label' => 'Engineering',
    'description' => 'Software and infrastructure claims where version, environment, and boundary conditions matter.',
    'enabled_checks' => [
        RiskCheckKind::EvidenceStrength->value,
        RiskCheckKind::OverGeneralization->value,
        RiskCheckKind::BoundaryCondition->value,
        RiskCheckKind::RedFlag->value,
    ],
    'min_tier' => [
        ClaimAssertiveness::Tentative->value => EvidenceTier::Blog->value,
        ClaimAssertiveness::Likely->value => EvidenceTier::Official->value,
        ClaimAssertiveness::Definitive->value => EvidenceTier::Official->value,
    ],
    'checks' => [
        RiskCheckKind::EvidenceStrength->value => [
            'verdict' => RiskVerdict::Soften->value,
        ],
        RiskCheckKind::OverGeneralization->value => [
            'verdict' => RiskVerdict::Soften->value,
            'keywords' => ['always', 'never', 'guaranteed', 'drop-in', 'zero downtime'],
        ],
        RiskCheckKind::BoundaryCondition->value => [
            'verdict' => RiskVerdict::FlagForHumanReview->value,
            'keywords' => ['all versions', 'any database', 'any runtime', 'any workload'],
        ],
        RiskCheckKind::RedFlag->value => [
            'verdict' => RiskVerdict::FlagForHumanReview->value,
            'keywords' => ['security bypass', 'data loss', 'privilege escalation', 'production outage'],
        ],
    ],
];
