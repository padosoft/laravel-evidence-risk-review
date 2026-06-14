<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Mcp;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\EvidenceRiskReview;
use Padosoft\EvidenceRiskReview\Support\ArrayData;

final readonly class McpToolRegistry
{
    public const ASSESS = 'evidence_review.assess';

    public const LABEL_TIER = 'evidence_review.label_tier';

    public const LIST_PROFILES = 'evidence_review.list_profiles';

    public function __construct(
        private EvidenceRiskReview $reviews,
    ) {}

    /**
     * @return array<string, McpToolDefinition>
     */
    public function definitions(): array
    {
        return [
            self::ASSESS => new McpToolDefinition(
                name: self::ASSESS,
                description: 'Assess an answer artifact for evidence and risk guardrails.',
                inputSchema: $this->assessInputSchema(),
                outputSchema: $this->objectSchema('Review result payload.'),
            ),
            self::LABEL_TIER => new McpToolDefinition(
                name: self::LABEL_TIER,
                description: 'Label one source with the configured evidence tier taxonomy.',
                inputSchema: $this->labelTierInputSchema(),
                outputSchema: $this->objectSchema('Evidence tier payload.'),
            ),
            self::LIST_PROFILES => new McpToolDefinition(
                name: self::LIST_PROFILES,
                description: 'List configured evidence risk review profiles.',
                inputSchema: $this->objectSchema('No input is required.'),
                outputSchema: $this->objectSchema('Profile map payload.'),
            ),
        ];
    }

    public function definition(string $name): McpToolDefinition
    {
        return $this->definitions()[$name] ?? throw new InvalidArgumentException("Unknown MCP tool [{$name}].");
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(string $name, array $input = []): array
    {
        return match ($name) {
            self::ASSESS => $this->reviews->reviewArray($input),
            self::LABEL_TIER => ['tier' => $this->reviews->labelTier(ArrayData::requireMap($input, 'source'))->toArray()],
            self::LIST_PROFILES => ['profiles' => $this->reviews->listProfiles()],
            default => throw new InvalidArgumentException("Unknown MCP tool [{$name}]."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function assessInputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['artifact_id', 'answer_text'],
            'properties' => [
                'artifact_id' => ['type' => 'string'],
                'answer_text' => ['type' => 'string'],
                'question' => ['type' => ['string', 'null']],
                'tenant_id' => ['type' => ['string', 'null']],
                'claims' => ['type' => 'array', 'items' => ['type' => 'object']],
                'sources' => ['type' => 'array', 'items' => ['type' => 'object']],
                'metadata' => ['type' => 'object'],
                'options' => ['type' => 'object'],
            ],
            'additionalProperties' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function labelTierInputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'properties' => [
                'id' => ['type' => 'string'],
                'url' => ['type' => ['string', 'null']],
                'title' => ['type' => ['string', 'null']],
                'snippet' => ['type' => ['string', 'null']],
                'declared_tier' => ['type' => ['string', 'null']],
                'metadata' => ['type' => 'object'],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function objectSchema(string $description): array
    {
        return [
            'type' => 'object',
            'description' => $description,
            'additionalProperties' => true,
        ];
    }
}
