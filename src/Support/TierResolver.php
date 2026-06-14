<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\ValueObjects\EvidenceTierValue;

final class TierResolver
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function resolve(string|EvidenceTier|EvidenceTierValue|null $tier): EvidenceTierValue
    {
        if ($tier instanceof EvidenceTierValue) {
            return $tier;
        }

        if ($tier instanceof EvidenceTier) {
            $tier = $tier->value;
        }

        if ($tier === null || trim($tier) === '') {
            return $this->unverified();
        }

        return $this->all()[trim($tier)] ?? $this->unverified();
    }

    public function resolveConfigured(string $tier): EvidenceTierValue
    {
        $key = trim($tier);
        $tiers = $this->all();

        if ($key === '' || ! isset($tiers[$key])) {
            throw new InvalidArgumentException("Evidence tier [{$tier}] is not configured.");
        }

        return $tiers[$key];
    }

    /**
     * @return array<string, EvidenceTierValue>
     */
    public function all(): array
    {
        $configured = $this->configuredTiers();
        $tiers = [];

        foreach (EvidenceTier::cases() as $tier) {
            $tiers[$tier->value] = $this->fromConfig($tier->value, $configured[$tier->value] ?? [], $tier);
        }

        foreach ($configured as $key => $definition) {
            if (isset($tiers[$key])) {
                continue;
            }

            $tiers[$key] = $this->fromConfig($key, $definition, null);
        }

        uasort(
            $tiers,
            static fn (EvidenceTierValue $left, EvidenceTierValue $right): int => $right->rank <=> $left->rank
                ?: $left->key <=> $right->key,
        );

        return $tiers;
    }

    public function unverified(): EvidenceTierValue
    {
        return $this->all()[EvidenceTier::Unverified->value];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function configuredTiers(): array
    {
        $configured = $this->config->get('evidence-risk-review.tiers', []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException('Configured tiers must be an array.');
        }

        $tiers = [];

        foreach ($configured as $key => $definition) {
            if (! is_string($key) || $key === '') {
                throw new InvalidArgumentException('Configured tier keys must be non-empty strings.');
            }

            $tiers[$key] = ArrayData::requireMap($definition, "tiers.{$key}");
        }

        return $tiers;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function fromConfig(string $key, array $definition, ?EvidenceTier $builtIn): EvidenceTierValue
    {
        $rank = $definition['rank'] ?? $builtIn?->rank();
        $label = $definition['label'] ?? $builtIn?->label();

        if (! is_int($rank)) {
            throw new InvalidArgumentException("Tier [{$key}] must define an integer rank.");
        }

        if (! is_string($label) || $label === '') {
            throw new InvalidArgumentException("Tier [{$key}] must define a non-empty label.");
        }

        return new EvidenceTierValue(
            key: $key,
            rank: $rank,
            label: $label,
            builtin: $builtIn !== null,
        );
    }
}
