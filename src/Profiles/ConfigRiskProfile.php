<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Profiles;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Contracts\RiskProfileContract;
use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Support\ArrayData;
use ValueError;

final readonly class ConfigRiskProfile implements RiskProfileContract
{
    /**
     * @var list<string>
     */
    private const KEYWORD_CHECKS = [
        RiskCheckKind::OverGeneralization->value,
        RiskCheckKind::SpecialPopulation->value,
        RiskCheckKind::Contraindication->value,
        RiskCheckKind::BoundaryCondition->value,
        RiskCheckKind::RedFlag->value,
    ];

    /**
     * @param  list<RiskCheckKind>  $enabledChecks
     * @param  array<string, string>  $minTiers
     * @param  array<string, array<string, mixed>>  $checks
     */
    public function __construct(
        private string $key,
        private string $label,
        private string $description,
        private array $enabledChecks,
        private array $minTiers,
        private array $checks,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(string $key, array $payload): self
    {
        $enabledChecks = array_key_exists('enabled_checks', $payload) ? $payload['enabled_checks'] : null;
        $minTiers = array_key_exists('min_tier', $payload) ? $payload['min_tier'] : null;
        $checks = array_key_exists('checks', $payload) ? $payload['checks'] : [];

        if (! is_array($enabledChecks) || ! array_is_list($enabledChecks)) {
            throw new InvalidArgumentException("Profile [{$key}] enabled_checks must be a list.");
        }

        if (! is_array($minTiers)) {
            throw new InvalidArgumentException("Profile [{$key}] min_tier must be a map.");
        }

        if (! is_array($checks)) {
            throw new InvalidArgumentException("Profile [{$key}] checks must be a map.");
        }

        $parsedEnabledChecks = self::checkKinds($enabledChecks, $key);
        $parsedChecks = self::settingsMap($checks, "profiles.{$key}.checks");
        self::requireKeywordSettings($parsedEnabledChecks, $parsedChecks, $key);

        return new self(
            key: $key,
            label: ArrayData::string($payload, 'label'),
            description: ArrayData::string($payload, 'description'),
            enabledChecks: $parsedEnabledChecks,
            minTiers: self::minimumTierMap($minTiers, $key),
            checks: $parsedChecks,
        );
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function enabledChecks(): array
    {
        return $this->enabledChecks;
    }

    public function enables(RiskCheckKind $kind): bool
    {
        return in_array($kind, $this->enabledChecks, true);
    }

    public function minimumTierFor(ClaimAssertiveness $assertiveness): string
    {
        return $this->minTiers[$assertiveness->value];
    }

    public function settingsFor(RiskCheckKind $kind): array
    {
        return $this->checks[$kind->value] ?? [];
    }

    public function verdictFor(RiskCheckKind $kind): RiskVerdict
    {
        $settings = $this->settingsFor($kind);
        $verdict = array_key_exists('verdict', $settings)
            ? $settings['verdict']
            : RiskVerdict::FlagForHumanReview->value;

        if (! is_string($verdict)) {
            throw new InvalidArgumentException("Profile [{$this->key}] verdict for [{$kind->value}] must be a string.");
        }

        return RiskVerdict::from($verdict);
    }

    public function metadata(): RiskProfileMetadata
    {
        return new RiskProfileMetadata(
            key: $this->key,
            label: $this->label,
            description: $this->description,
            enabledChecks: array_map(
                static fn (RiskCheckKind $kind): string => $kind->value,
                $this->enabledChecks,
            ),
        );
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<RiskCheckKind>
     */
    private static function checkKinds(array $values, string $profileKey): array
    {
        $kinds = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException("Profile [{$profileKey}] enabled_checks must contain strings.");
            }

            $kinds[] = RiskCheckKind::from($value);
        }

        return $kinds;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, string>
     */
    private static function stringMap(array $values, string $context): array
    {
        $map = [];

        foreach ($values as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                throw new InvalidArgumentException("Expected string map at [{$context}].");
            }

            $map[$key] = $value;
        }

        return $map;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, string>
     */
    private static function minimumTierMap(array $values, string $profileKey): array
    {
        $map = self::stringMap($values, "profiles.{$profileKey}.min_tier");

        foreach (ClaimAssertiveness::cases() as $assertiveness) {
            if (! array_key_exists($assertiveness->value, $map)) {
                throw new InvalidArgumentException("Profile [{$profileKey}] min_tier missing [{$assertiveness->value}].");
            }
        }

        return $map;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, array<string, mixed>>
     */
    private static function settingsMap(array $values, string $context): array
    {
        $map = [];

        foreach ($values as $key => $value) {
            if (! is_string($key) || ! is_array($value)) {
                throw new InvalidArgumentException("Expected settings map at [{$context}].");
            }

            try {
                RiskCheckKind::from($key);
            } catch (ValueError) {
                throw new InvalidArgumentException("Unknown risk check settings key [{$context}.{$key}].");
            }

            $settings = ArrayData::requireMap($value, "{$context}.{$key}");
            self::validateKeywordList($settings, "{$context}.{$key}.keywords");

            $map[$key] = $settings;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function validateKeywordList(array $settings, string $context): void
    {
        if (! array_key_exists('keywords', $settings)) {
            return;
        }

        $keywords = $settings['keywords'];

        if (! is_array($keywords) || ! array_is_list($keywords)) {
            throw new InvalidArgumentException("Expected keyword list at [{$context}].");
        }

        foreach ($keywords as $keyword) {
            if (! is_string($keyword) || $keyword === '') {
                throw new InvalidArgumentException("Expected non-empty string keywords at [{$context}].");
            }
        }
    }

    /**
     * @param  list<RiskCheckKind>  $enabledChecks
     * @param  array<string, array<string, mixed>>  $checks
     */
    private static function requireKeywordSettings(array $enabledChecks, array $checks, string $profileKey): void
    {
        foreach ($enabledChecks as $kind) {
            if (! in_array($kind->value, self::KEYWORD_CHECKS, true)) {
                continue;
            }

            if (! isset($checks[$kind->value]) || ! array_key_exists('keywords', $checks[$kind->value])) {
                throw new InvalidArgumentException("Profile [{$profileKey}] check [{$kind->value}] must define keywords.");
            }
        }
    }
}
