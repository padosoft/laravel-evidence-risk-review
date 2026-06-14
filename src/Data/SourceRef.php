<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Data;

use Padosoft\EvidenceRiskReview\Support\ArrayData;

final readonly class SourceRef
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public ?string $url = null,
        public ?string $title = null,
        public ?string $snippet = null,
        public ?string $declaredTier = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            id: ArrayData::string($payload, 'id'),
            url: ArrayData::nullableString($payload, 'url'),
            title: ArrayData::nullableString($payload, 'title'),
            snippet: ArrayData::nullableString($payload, 'snippet'),
            declaredTier: ArrayData::nullableString($payload, 'declared_tier')
                ?? ArrayData::nullableString($payload, 'declaredTier'),
            metadata: ArrayData::map($payload, 'metadata'),
        );
    }

    /**
     * @return array{
     *     id: string,
     *     url: string|null,
     *     title: string|null,
     *     snippet: string|null,
     *     declared_tier: string|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'title' => $this->title,
            'snippet' => $this->snippet,
            'declared_tier' => $this->declaredTier,
            'metadata' => $this->metadata,
        ];
    }
}
