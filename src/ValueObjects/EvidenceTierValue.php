<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\ValueObjects;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Support\ArrayData;

final readonly class EvidenceTierValue
{
    public function __construct(
        public string $key,
        public int $rank,
        public string $label,
        public bool $builtin = false,
    ) {
        if ($this->key === '') {
            throw new InvalidArgumentException('Evidence tier key must not be empty.');
        }

        if ($this->rank < 0 || $this->rank > 100) {
            throw new InvalidArgumentException('Evidence tier rank must be between 0 and 100.');
        }

        if ($this->label === '') {
            throw new InvalidArgumentException('Evidence tier label must not be empty.');
        }
    }

    public static function fromBuiltIn(EvidenceTier $tier): self
    {
        return new self($tier->value, $tier->rank(), $tier->label(), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $rank = $payload['rank'] ?? null;
        $builtin = $payload['builtin'] ?? false;

        if (! is_int($rank)) {
            throw new InvalidArgumentException('Expected integer at [rank].');
        }

        if (! is_bool($builtin)) {
            throw new InvalidArgumentException('Expected boolean at [builtin].');
        }

        return new self(
            ArrayData::string($payload, 'key'),
            $rank,
            ArrayData::string($payload, 'label'),
            $builtin,
        );
    }

    /**
     * @return array{key: string, rank: int, label: string, builtin: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'rank' => $this->rank,
            'label' => $this->label,
            'builtin' => $this->builtin,
        ];
    }
}
