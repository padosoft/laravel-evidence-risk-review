<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Mcp;

final class LaravelMcpToolAdapter
{
    private const LARAVEL_MCP_TOOL_CLASS = 'Laravel\\Mcp\\Server\\Tool';

    public function available(): bool
    {
        return class_exists(self::LARAVEL_MCP_TOOL_CLASS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toLaravelPayload(McpToolDefinition $definition): ?array
    {
        if (! $this->available()) {
            return null;
        }

        return [
            'name' => $definition->name,
            'description' => $definition->description,
            'inputSchema' => $definition->inputSchema,
            'outputSchema' => $definition->outputSchema,
        ];
    }
}
