<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\ValueObjects;

use InvalidArgumentException;

final readonly class ReviewBudget
{
    public function __construct(
        public int $maxLlmCalls = 3,
        public int $maxTokens = 6000,
        public int $maxHeavyChecks = 8,
        public int $maxWallSeconds = 30,
    ) {
        foreach ([
            'maxLlmCalls' => $this->maxLlmCalls,
            'maxTokens' => $this->maxTokens,
            'maxHeavyChecks' => $this->maxHeavyChecks,
            'maxWallSeconds' => $this->maxWallSeconds,
        ] as $key => $value) {
            if ($value < 0) {
                throw new InvalidArgumentException("Budget [{$key}] must be zero or greater.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            maxLlmCalls: self::integer($payload, 'max_llm_calls', 3),
            maxTokens: self::integer($payload, 'max_tokens', 6000),
            maxHeavyChecks: self::integer($payload, 'max_heavy_checks', 8),
            maxWallSeconds: self::integer($payload, 'max_wall_seconds', 30),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function integer(array $payload, string $key, int $default): int
    {
        $value = array_key_exists($key, $payload) ? $payload[$key] : $default;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidArgumentException("Budget [{$key}] must be an integer.");
    }
}
