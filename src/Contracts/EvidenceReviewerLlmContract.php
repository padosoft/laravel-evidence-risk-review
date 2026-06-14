<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Contracts;

use Padosoft\EvidenceRiskReview\Data\LlmRequest;
use Padosoft\EvidenceRiskReview\Data\LlmResponse;

interface EvidenceReviewerLlmContract
{
    public function complete(LlmRequest $request): LlmResponse;
}
