<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Mcp\LaravelMcpToolAdapter;
use Padosoft\EvidenceRiskReview\Mcp\McpToolRegistry;
use Padosoft\EvidenceRiskReview\Tests\TestCase;

final class McpToolRegistryTest extends TestCase
{
    public function test_registry_exposes_required_tool_definitions(): void
    {
        $registry = $this->resolve(McpToolRegistry::class);
        $definitions = $registry->definitions();

        self::assertSame([
            McpToolRegistry::ASSESS,
            McpToolRegistry::LABEL_TIER,
            McpToolRegistry::LIST_PROFILES,
        ], array_keys($definitions));

        foreach ($definitions as $definition) {
            self::assertSame($definition->name, $definition->toArray()['name']);
            self::assertSame('object', $definition->inputSchema['type']);
            self::assertSame('object', $definition->outputSchema['type']);
        }
    }

    public function test_assess_tool_reviews_artifacts(): void
    {
        $result = $this->resolve(McpToolRegistry::class)->handle(McpToolRegistry::ASSESS, [
            'artifact_id' => 'mcp-1',
            'answer_text' => 'No claims to check.',
            'options' => ['dry_run' => true],
        ]);

        self::assertSame('mcp-1', $result['artifact_id']);
        self::assertSame([], $result['findings']);
    }

    public function test_label_tier_tool_labels_sources(): void
    {
        $result = $this->resolve(McpToolRegistry::class)->handle(McpToolRegistry::LABEL_TIER, [
            'id' => 's1',
            'declared_tier' => EvidenceTier::Official->value,
        ]);

        self::assertSame(EvidenceTier::Official->value, $result['tier']['key']);
    }

    public function test_list_profiles_tool_returns_profiles(): void
    {
        $result = $this->resolve(McpToolRegistry::class)->handle(McpToolRegistry::LIST_PROFILES);

        self::assertSame('default', $result['profiles']['default']['key']);
    }

    public function test_unknown_tool_fails_loudly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown MCP tool [missing].');

        $this->resolve(McpToolRegistry::class)->handle('missing');
    }

    public function test_laravel_mcp_adapter_does_not_force_optional_dependency(): void
    {
        $adapter = new LaravelMcpToolAdapter;
        $definition = $this->resolve(McpToolRegistry::class)->definition(McpToolRegistry::LIST_PROFILES);

        if (! class_exists('Laravel\\Mcp\\Server\\Tool')) {
            self::assertFalse($adapter->available());
            self::assertNull($adapter->toLaravelPayload($definition));

            return;
        }

        self::assertTrue($adapter->available());
        self::assertSame(McpToolRegistry::LIST_PROFILES, $adapter->toLaravelPayload($definition)['name'] ?? null);
    }
}
