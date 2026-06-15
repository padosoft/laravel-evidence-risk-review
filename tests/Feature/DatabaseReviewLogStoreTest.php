<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Feature;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Log\DatabaseReviewLogStore;
use Padosoft\EvidenceRiskReview\Support\ReviewEngine;
use Padosoft\EvidenceRiskReview\Tests\TestCase;

final class DatabaseReviewLogStoreTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_database_log_store_appends_review_rows_to_sqlite(): void
    {
        $connection = $this->resolve(DatabaseManager::class)->connection();
        $this->createLogTable($connection);
        $store = new DatabaseReviewLogStore($connection);
        $engine = $this->resolve(ReviewEngine::class);
        $result = $engine->review(new ReviewArtifact('artifact-db', 'Answer'), new ReviewOptions(dryRun: true));

        $store->append(new ReviewArtifact('artifact-db', 'Answer'), new ReviewOptions, $result);

        $row = $connection->table('evidence_risk_review_logs')->first();

        self::assertNotNull($row);
        self::assertSame('artifact-db', $row->artifact_id);
        self::assertSame($result->reviewId, $row->review_id);
    }

    private function createLogTable(Connection $connection): void
    {
        $connection->getSchemaBuilder()->create('evidence_risk_review_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('review_id')->index();
            $table->string('artifact_id')->index();
            $table->string('profile_key')->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('max_verdict')->default('keep')->index();
            $table->decimal('risk_score', 5, 4)->default(0);
            $table->json('findings');
            $table->json('claim_verdicts');
            $table->json('source_tiers');
            $table->json('budget');
            $table->json('artifact');
            $table->json('options');
            $table->json('metadata');
            $table->timestamp('reviewed_at');
            $table->timestamp('created_at');
        });
    }
}
