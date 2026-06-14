<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Llm;

use Padosoft\EvidenceRiskReview\Contracts\EvidenceReviewerLlmContract;
use Padosoft\EvidenceRiskReview\Data\LlmRequest;
use Padosoft\EvidenceRiskReview\Data\LlmResponse;

final class NullEvidenceReviewerLlm implements EvidenceReviewerLlmContract
{
    public function complete(LlmRequest $request): LlmResponse
    {
        return new LlmResponse(data: [
            'findings' => [],
            'source_tiers' => [],
        ]);
    }
}
