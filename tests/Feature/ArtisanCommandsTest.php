<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Feature;

use Illuminate\Testing\PendingCommand;
use JsonException;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Tests\TestCase;

final class ArtisanCommandsTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_review_command_returns_success_exit_code_when_clean(): void
    {
        $path = $this->jsonFile([
            'artifact_id' => 'cmd-clean',
            'answer_text' => 'No claims to check.',
        ]);

        try {
            $this->pendingArtisan('evidence:review', ['path' => $path, '--dry-run' => true])
                ->expectsOutputToContain('"artifact_id":"cmd-clean"')
                ->assertExitCode(0);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @throws JsonException
     */
    public function test_review_command_returns_flagged_exit_code_when_findings_exist(): void
    {
        $path = $this->jsonFile([
            'artifact_id' => 'cmd-flagged',
            'answer_text' => 'This always cures the condition.',
            'claims' => [[
                'id' => 'c1',
                'text' => 'This always cures the condition.',
                'assertiveness' => 'definitive',
                'source_ids' => ['s1'],
            ]],
            'sources' => [[
                'id' => 's1',
                'declared_tier' => EvidenceTier::Blog->value,
            ]],
        ]);

        try {
            $this->pendingArtisan('evidence:review', ['path' => $path, '--dry-run' => true])
                ->expectsOutputToContain('"artifact_id":"cmd-flagged"')
                ->assertExitCode(2);
        } finally {
            @unlink($path);
        }
    }

    public function test_review_command_returns_failure_exit_code_for_invalid_json(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'err-review-');
        self::assertIsString($path);
        file_put_contents($path, '{not-json');

        try {
            $this->pendingArtisan('evidence:review', ['path' => $path])
                ->assertExitCode(1);
        } finally {
            @unlink($path);
        }
    }

    public function test_profiles_taxonomy_and_log_commands_return_json(): void
    {
        $this->pendingArtisan('evidence:profiles')
            ->expectsOutputToContain('"profiles"')
            ->assertExitCode(0);

        $this->pendingArtisan('evidence:taxonomy')
            ->expectsOutputToContain('"risk_checks"')
            ->assertExitCode(0);

        $this->pendingArtisan('evidence:log')
            ->expectsOutputToContain('"store":"null"')
            ->assertExitCode(0);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function pendingArtisan(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        if (is_int($pending)) {
            self::fail("Expected pending Artisan command for [{$command}], got exit code [{$pending}].");
        }

        return $pending;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function jsonFile(array $payload): string
    {
        $path = tempnam(sys_get_temp_dir(), 'evidence-review-');
        self::assertIsString($path);

        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        return $path;
    }
}
