<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Data;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Support\ArrayData;

final readonly class ReviewArtifact
{
    /**
     * @param  list<ClaimRef>  $claims
     * @param  list<SourceRef>  $sources
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $artifactId,
        public string $answerText,
        public array $claims = [],
        public array $sources = [],
        public ?string $question = null,
        public ?string $tenantId = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            artifactId: ArrayData::string($payload, 'artifact_id'),
            answerText: ArrayData::string($payload, 'answer_text'),
            claims: self::claimList(array_key_exists('claims', $payload) ? $payload['claims'] : []),
            sources: self::sourceList(array_key_exists('sources', $payload) ? $payload['sources'] : []),
            question: ArrayData::nullableString($payload, 'question'),
            tenantId: ArrayData::nullableString($payload, 'tenant_id'),
            metadata: ArrayData::map($payload, 'metadata'),
        );
    }

    /**
     * @return array{
     *     artifact_id: string,
     *     answer_text: string,
     *     question: string|null,
     *     tenant_id: string|null,
     *     claims: list<array{id: string, text: string, assertiveness: string, source_ids: list<string>, metadata: array<string, mixed>}>,
     *     sources: list<array{id: string, url: string|null, title: string|null, snippet: string|null, declared_tier: string|null, metadata: array<string, mixed>}>,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'artifact_id' => $this->artifactId,
            'answer_text' => $this->answerText,
            'question' => $this->question,
            'tenant_id' => $this->tenantId,
            'claims' => array_map(
                static fn (ClaimRef $claim): array => $claim->toArray(),
                $this->claims,
            ),
            'sources' => array_map(
                static fn (SourceRef $source): array => $source->toArray(),
                $this->sources,
            ),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @return list<ClaimRef>
     */
    private static function claimList(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Expected list at [claims].');
        }

        if (! array_is_list($value)) {
            throw new InvalidArgumentException('Expected list at [claims], associative array given.');
        }

        $claims = [];

        foreach ($value as $index => $item) {
            $claims[] = ClaimRef::fromArray(ArrayData::requireMap($item, "claims.{$index}"));
        }

        return $claims;
    }

    /**
     * @return list<SourceRef>
     */
    private static function sourceList(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Expected list at [sources].');
        }

        if (! array_is_list($value)) {
            throw new InvalidArgumentException('Expected list at [sources], associative array given.');
        }

        $sources = [];

        foreach ($value as $index => $item) {
            $sources[] = SourceRef::fromArray(ArrayData::requireMap($item, "sources.{$index}"));
        }

        return $sources;
    }
}
