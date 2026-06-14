<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Profiles;

final readonly class RiskProfileMetadata
{
    /**
     * @param  list<string>  $enabledChecks
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public array $enabledChecks,
    ) {}

    /**
     * @return array{key: string, label: string, description: string, enabled_checks: list<string>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'enabled_checks' => $this->enabledChecks,
        ];
    }
}
