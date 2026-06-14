<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Llm;

use Closure;
use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Contracts\EvidenceReviewerLlmContract;
use Padosoft\EvidenceRiskReview\Data\LlmRequest;
use Padosoft\EvidenceRiskReview\Data\LlmResponse;

final class CallbackEvidenceReviewerLlm implements EvidenceReviewerLlmContract
{
    private Closure $callback;

    public function __construct(callable $callback)
    {
        $this->callback = Closure::fromCallable($callback);
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $response = ($this->callback)($request);

        if (! $response instanceof LlmResponse) {
            throw new InvalidArgumentException('LLM callback must return an LlmResponse.');
        }

        return $response;
    }
}
