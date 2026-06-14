<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Profiles;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Contracts\RiskProfileContract;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Exceptions\ProfileNotFoundException;
use Padosoft\EvidenceRiskReview\Support\ArrayData;

final class DomainProfileRegistry
{
    /**
     * @var array<string, RiskProfileContract>
     */
    private array $profiles = [];

    public function __construct(
        private readonly ConfigRepository $config,
    ) {
        $this->loadConfiguredProfiles();
    }

    public function register(string $key, RiskProfileContract $profile): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Profile key must not be empty.');
        }

        $this->profiles[$key] = $profile;
    }

    public function get(string $key): RiskProfileContract
    {
        return $this->profiles[$key] ?? throw ProfileNotFoundException::forKey($key);
    }

    public function has(string $key): bool
    {
        return isset($this->profiles[$key]);
    }

    /**
     * @return array<string, RiskProfileMetadata>
     */
    public function all(): array
    {
        $profiles = [];

        foreach ($this->profiles as $key => $profile) {
            $profiles[$key] = $profile instanceof ConfigRiskProfile
                ? $profile->metadata()
                : new RiskProfileMetadata(
                    key: $profile->key(),
                    label: $profile->label(),
                    description: $profile->description(),
                    enabledChecks: array_map(
                        static fn (RiskCheckKind $kind): string => $kind->value,
                        $profile->enabledChecks(),
                    ),
                );
        }

        ksort($profiles);

        return $profiles;
    }

    public function default(): RiskProfileContract
    {
        $key = $this->config->get('evidence-risk-review.default_profile', 'default');

        if (! is_string($key)) {
            throw new InvalidArgumentException('Default profile key must be a string.');
        }

        return $this->get($key);
    }

    private function loadConfiguredProfiles(): void
    {
        $profiles = $this->config->get('evidence-risk-review.profiles', []);

        if (! is_array($profiles)) {
            throw new InvalidArgumentException('Configured profiles must be an array.');
        }

        foreach ($profiles as $key => $payload) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Configured profile keys must be strings.');
            }

            $this->register($key, ConfigRiskProfile::fromArray($key, ArrayData::requireMap($payload, "profiles.{$key}")));
        }
    }
}
