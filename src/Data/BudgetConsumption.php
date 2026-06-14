<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Data;

final readonly class BudgetConsumption
{
    public function __construct(
        public int $llmCalls,
        public int $tokens,
        public int $heavyChecks,
        public int $wallSeconds,
    ) {}

    /**
     * @return array{llm_calls: int, tokens: int, heavy_checks: int, wall_seconds: int}
     */
    public function toArray(): array
    {
        return [
            'llm_calls' => $this->llmCalls,
            'tokens' => $this->tokens,
            'heavy_checks' => $this->heavyChecks,
            'wall_seconds' => $this->wallSeconds,
        ];
    }
}
