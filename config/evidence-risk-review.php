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

    'review_log' => [
        'store' => env('EVIDENCE_RISK_REVIEW_LOG_STORE', 'null'),
        'connection' => env('EVIDENCE_RISK_REVIEW_LOG_CONNECTION'),
        'table' => env('EVIDENCE_RISK_REVIEW_LOG_TABLE', 'evidence_risk_review_logs'),
    ],

    'budget' => [
        'max_llm_calls' => env('EVIDENCE_RISK_REVIEW_MAX_LLM_CALLS', 3),
        'max_tokens' => env('EVIDENCE_RISK_REVIEW_MAX_TOKENS', 6000),
        'max_heavy_checks' => env('EVIDENCE_RISK_REVIEW_MAX_HEAVY_CHECKS', 8),
        'max_wall_seconds' => env('EVIDENCE_RISK_REVIEW_MAX_WALL_SECONDS', 30),
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

    'profiles' => [
        'default' => require __DIR__.'/evidence-risk-review/profiles/default.php',
        'engineering' => require __DIR__.'/evidence-risk-review/profiles/engineering.php',
        'medical' => require __DIR__.'/evidence-risk-review/profiles/medical.php',
        'legal' => require __DIR__.'/evidence-risk-review/profiles/legal.php',
        'finance' => require __DIR__.'/evidence-risk-review/profiles/finance.php',
    ],
];
