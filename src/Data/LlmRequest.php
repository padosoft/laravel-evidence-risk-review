<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Data;

final readonly class LlmRequest
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $purpose,
        public string $prompt,
        public array $payload = [],
        public int $maxTokens = 1000,
    ) {}

    /**
     * @return array{purpose: string, prompt: string, payload: array<string, mixed>, max_tokens: int}
     */
    public function toArray(): array
    {
        return [
            'purpose' => $this->purpose,
            'prompt' => $this->prompt,
            'payload' => $this->payload,
            'max_tokens' => $this->maxTokens,
        ];
    }
}
