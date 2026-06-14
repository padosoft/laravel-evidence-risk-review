<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Data;

use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Support\ArrayData;

final readonly class ClaimRef
{
    /**
     * @param  list<string>  $sourceIds
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $text,
        public ClaimAssertiveness $assertiveness = ClaimAssertiveness::Likely,
        public array $sourceIds = [],
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $assertiveness = ArrayData::nullableString($payload, 'assertiveness') ?? ClaimAssertiveness::Likely->value;

        return new self(
            id: ArrayData::string($payload, 'id'),
            text: ArrayData::string($payload, 'text'),
            assertiveness: ClaimAssertiveness::from($assertiveness),
            sourceIds: ArrayData::stringList($payload, 'source_ids'),
            metadata: ArrayData::map($payload, 'metadata'),
        );
    }

    /**
     * @return array{
     *     id: string,
     *     text: string,
     *     assertiveness: string,
     *     source_ids: list<string>,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'assertiveness' => $this->assertiveness->value,
            'source_ids' => $this->sourceIds,
            'metadata' => $this->metadata,
        ];
    }
}
