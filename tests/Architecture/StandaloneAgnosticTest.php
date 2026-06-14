<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class StandaloneAgnosticTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_NEEDLES = [
        'AskMyDocs',
        'lopadova/askmydocs',
        'padosoft/askmydocs',
        'padosoft/askmydocs-pro',
        'KnowledgeDocument',
        'KbSearchService',
        'knowledge_documents',
        'knowledge_chunks',
        'kb_nodes',
        'kb_edges',
        'kb_canonical_audit',
    ];

    #[Test]
    public function src_contains_no_host_specific_coupling(): void
    {
        $root = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'src';

        foreach ($this->phpFiles($root) as $file) {
            $contents = file_get_contents($file->getPathname());

            self::assertIsString($contents);

            foreach (self::FORBIDDEN_NEEDLES as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $contents,
                    "Forbidden host coupling [{$needle}] found in {$file->getPathname()}",
                );
            }
        }
    }

    #[Test]
    public function composer_dependencies_do_not_require_host_packages(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);

        $requires = array_merge(
            $this->stringKeyedMap($composer['require'] ?? []),
            $this->stringKeyedMap($composer['require-dev'] ?? []),
        );

        foreach (self::FORBIDDEN_NEEDLES as $needle) {
            self::assertArrayNotHasKey($needle, $requires);
        }
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyedMap(mixed $value): array
    {
        self::assertIsArray($value);

        foreach (array_keys($value) as $key) {
            self::assertIsString($key);
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
