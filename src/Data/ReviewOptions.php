<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Data;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\ValueObjects\ReviewBudget;

final readonly class ReviewOptions
{
    public function __construct(
        public string $profileKey = 'default',
        public ?ReviewBudget $budget = null,
        public bool $labelViaLlm = false,
        public bool $dryRun = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $profileKey = array_key_exists('profile_key', $payload) ? $payload['profile_key'] : 'default';
        $budget = array_key_exists('budget', $payload) ? $payload['budget'] : null;

        if (! is_string($profileKey) || $profileKey === '') {
            throw new InvalidArgumentException('Review option [profile_key] must be a non-empty string.');
        }

        if ($budget !== null && ! is_array($budget)) {
            throw new InvalidArgumentException('Review option [budget] must be an object map or null.');
        }

        /** @var array<string, mixed>|null $budget */
        return new self(
            profileKey: $profileKey,
            budget: $budget === null ? null : ReviewBudget::fromArray($budget),
            labelViaLlm: self::boolean($payload, 'label_via_llm', false),
            dryRun: self::boolean($payload, 'dry_run', false),
        );
    }

    /**
     * @return array{profile_key: string, budget: array<string, int>|null, label_via_llm: bool, dry_run: bool}
     */
    public function toArray(): array
    {
        return [
            'profile_key' => $this->profileKey,
            'budget' => $this->budget?->toArray(),
            'label_via_llm' => $this->labelViaLlm,
            'dry_run' => $this->dryRun,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function boolean(array $payload, string $key, bool $default): bool
    {
        $value = array_key_exists($key, $payload) ? $payload[$key] : $default;

        if (is_bool($value)) {
            return $value;
        }

        throw new InvalidArgumentException("Review option [{$key}] must be a boolean.");
    }
}
