<?php

declare(strict_types=1);

use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;

return [
    'default_profile' => env('EVIDENCE_RISK_REVIEW_PROFILE', 'default'),

    'api' => [
        'enabled' => env('EVIDENCE_RISK_REVIEW_API_ENABLED', false),
        'prefix' => env('EVIDENCE_RISK_REVIEW_API_PREFIX', 'evidence-risk-review'),
        'middleware' => [],
    ],

    'mcp' => [
        'enabled' => env('EVIDENCE_RISK_REVIEW_MCP_ENABLED', false),
    ],

    'llm' => [
        'enabled' => env('EVIDENCE_RISK_REVIEW_LLM_ENABLED', false),
    ],

    'tiers' => [
        EvidenceTier::Guideline->value => ['rank' => 100, 'label' => 'Guideline'],
        EvidenceTier::PeerReviewed->value => ['rank' => 80, 'label' => 'Peer-reviewed'],
        EvidenceTier::Official->value => ['rank' => 70, 'label' => 'Official source'],
        EvidenceTier::Preprint->value => ['rank' => 65, 'label' => 'Preprint'],
        EvidenceTier::News->value => ['rank' => 45, 'label' => 'News'],
        EvidenceTier::Blog->value => ['rank' => 30, 'label' => 'Blog'],
        EvidenceTier::SearchHint->value => ['rank' => 15, 'label' => 'Search hint'],
        EvidenceTier::Unverified->value => ['rank' => 0, 'label' => 'Unverified'],
    ],

    'tier_hints' => [],
];
