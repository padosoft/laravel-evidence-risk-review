<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Data;

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
}
