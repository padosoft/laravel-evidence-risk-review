<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Support;

use Padosoft\EvidenceRiskReview\Data\BudgetConsumption;
use Padosoft\EvidenceRiskReview\ValueObjects\ReviewBudget;

final class BudgetMeter
{
    private int $llmCalls = 0;

    private int $tokens = 0;

    private int $heavyChecks = 0;

    private readonly int $startedAt;

    public function __construct(
        private readonly ReviewBudget $budget,
    ) {
        $this->startedAt = time();
    }

    public function tryConsumeLlmCall(int $estTokens = 0): bool
    {
        $estTokens = max(0, $estTokens);

        if ($this->exhausted()) {
            return false;
        }

        if ($this->llmCalls + 1 > $this->budget->maxLlmCalls) {
            return false;
        }

        if ($this->heavyChecks + 1 > $this->budget->maxHeavyChecks) {
            return false;
        }

        if ($this->tokens + $estTokens > $this->budget->maxTokens) {
            return false;
        }

        $this->llmCalls++;
        $this->heavyChecks++;
        $this->tokens += $estTokens;

        return true;
    }

    public function recordTokens(int $tokens): void
    {
        $this->tokens += max(0, $tokens);
    }

    public function consumed(): BudgetConsumption
    {
        return new BudgetConsumption(
            llmCalls: $this->llmCalls,
            tokens: $this->tokens,
            heavyChecks: $this->heavyChecks,
            wallSeconds: max(0, time() - $this->startedAt),
        );
    }

    public function exhausted(): bool
    {
        return $this->tokens >= $this->budget->maxTokens
            || $this->llmCalls >= $this->budget->maxLlmCalls
            || $this->heavyChecks >= $this->budget->maxHeavyChecks
            || (time() - $this->startedAt) >= $this->budget->maxWallSeconds;
    }
}
