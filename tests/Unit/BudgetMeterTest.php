<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Support\BudgetMeter;
use Padosoft\EvidenceRiskReview\Tests\TestCase;
use Padosoft\EvidenceRiskReview\ValueObjects\ReviewBudget;

final class BudgetMeterTest extends TestCase
{
    public function test_it_tracks_llm_calls_tokens_and_heavy_checks(): void
    {
        $meter = new BudgetMeter(new ReviewBudget(maxLlmCalls: 2, maxTokens: 100, maxHeavyChecks: 2, maxWallSeconds: 30));

        self::assertTrue($meter->tryConsumeLlmCall(25));
        $meter->recordTokens(10);
        $meter->recordTokens(-50);

        $consumed = $meter->consumed();

        self::assertSame(1, $consumed->llmCalls);
        self::assertSame(35, $consumed->tokens);
        self::assertSame(1, $consumed->heavyChecks);
        self::assertGreaterThanOrEqual(0, $consumed->wallSeconds);
    }

    public function test_it_rejects_consumption_past_caps(): void
    {
        $meter = new BudgetMeter(new ReviewBudget(maxLlmCalls: 1, maxTokens: 10, maxHeavyChecks: 1, maxWallSeconds: 30));

        self::assertFalse($meter->tryConsumeLlmCall(11));
        self::assertTrue($meter->tryConsumeLlmCall(10));
        self::assertFalse($meter->tryConsumeLlmCall());
        self::assertTrue($meter->exhausted());
    }

    public function test_budget_from_array_requires_integer_non_negative_values(): void
    {
        $budget = ReviewBudget::fromArray([
            'max_llm_calls' => 5,
            'max_tokens' => '1000',
            'max_heavy_checks' => 7,
            'max_wall_seconds' => 12,
        ]);

        self::assertSame(5, $budget->maxLlmCalls);
        self::assertSame(1000, $budget->maxTokens);
        self::assertSame(7, $budget->maxHeavyChecks);
        self::assertSame(12, $budget->maxWallSeconds);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Budget [max_tokens] must be an integer.');

        ReviewBudget::fromArray(['max_tokens' => 'lots']);
    }

    public function test_budget_rejects_negative_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Budget [maxLlmCalls] must be zero or greater.');

        new ReviewBudget(maxLlmCalls: -1);
    }
}
