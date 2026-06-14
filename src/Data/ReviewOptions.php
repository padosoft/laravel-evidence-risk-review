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
}
