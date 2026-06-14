<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Mcp;

final readonly class McpToolDefinition
{
    /**
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>  $outputSchema
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
        public array $outputSchema,
    ) {}

    /**
     * @return array{name: string, description: string, input_schema: array<string, mixed>, output_schema: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $this->inputSchema,
            'output_schema' => $this->outputSchema,
        ];
    }
}
