<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Data;

final readonly class LlmResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $text = '',
        public array $data = [],
        public int $tokensUsed = 0,
    ) {}

    /**
     * @return array{text: string, data: array<string, mixed>, tokens_used: int}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'data' => $this->data,
            'tokens_used' => $this->tokensUsed,
        ];
    }
}
