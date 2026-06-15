<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Feature;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\Attributes\WithConfig;
use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use Padosoft\EvidenceRiskReview\Contracts\TenantResolver;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Log\DatabaseReviewLogStore;
use Padosoft\EvidenceRiskReview\Support\ReviewEngine;
use Padosoft\EvidenceRiskReview\Tests\TestCase;

/**
 * v1.1.0 — the paginated, tenant-scoped review-log list endpoint
 * (GET /reviews) and its read-scoping guarantees.
 */
#[WithConfig('evidence-risk-review.api.enabled', true, false)]
#[WithConfig('evidence-risk-review.review_log.store', 'database', false)]
final class HttpReviewListTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLogTable($this->resolve(DatabaseManager::class)->connection());
    }

    public function test_list_returns_the_paginated_shape_with_compact_rows(): void
    {
        $this->insertRow('rev-1', 'art-1', tenant: null, maxVerdict: 'soften', profile: 'default');
        $this->insertRow('rev-2', 'art-2', tenant: null, maxVerdict: 'keep', profile: 'medical');

        $this->getJson('/evidence-risk-review/api/reviews')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['review_id', 'artifact_id', 'profile_key', 'max_verdict', 'risk_score', 'tenant_id', 'created_at']],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ])
            ->assertJsonPath('total', 2)
            ->assertJsonPath('current_page', 1);
    }

    public function test_list_is_scoped_to_the_resolved_tenant(): void
    {
        $this->bindTenant('tenant-a');
        $this->insertRow('rev-a', 'art-a', tenant: 'tenant-a', maxVerdict: 'keep', profile: 'default');
        $this->insertRow('rev-b', 'art-b', tenant: 'tenant-b', maxVerdict: 'keep', profile: 'default');

        // Even with a client-supplied tenant filter, the resolver forces tenant-a.
        $response = $this->getJson('/evidence-risk-review/api/reviews?tenant=tenant-b')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.review_id', 'rev-a');

        self::assertSame('tenant-a', $response->json('data.0.tenant_id'));

        // The other tenant's review is unreachable by id as well (R30).
        $this->getJson('/evidence-risk-review/api/reviews/rev-b')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'unknown_review');

        $this->getJson('/evidence-risk-review/api/reviews/rev-a')
            ->assertOk()
            ->assertJsonPath('review_id', 'rev-a');
    }

    public function test_list_filters_by_profile_and_min_verdict(): void
    {
        $this->insertRow('rev-keep', 'art-1', tenant: null, maxVerdict: 'keep', profile: 'default');
        $this->insertRow('rev-soften', 'art-2', tenant: null, maxVerdict: 'soften', profile: 'default');
        $this->insertRow('rev-remove', 'art-3', tenant: null, maxVerdict: 'remove', profile: 'medical');

        // min_verdict=soften keeps soften + remove (severity >= 1), drops keep.
        $this->getJson('/evidence-risk-review/api/reviews?min_verdict=soften')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->getJson('/evidence-risk-review/api/reviews?profile=medical')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.review_id', 'rev-remove');
    }

    public function test_append_stamps_the_resolved_tenant_over_the_artifact(): void
    {
        $this->bindTenant('tenant-x');
        $store = $this->resolve(ReviewLogStore::class);
        self::assertInstanceOf(DatabaseReviewLogStore::class, $store);

        $engine = $this->resolve(ReviewEngine::class);
        $result = $engine->review(new ReviewArtifact('art-x', 'Answer', tenantId: 'spoofed'), new ReviewOptions(dryRun: true));
        $store->append(new ReviewArtifact('art-x', 'Answer', tenantId: 'spoofed'), new ReviewOptions, $result);

        $row = $this->resolve(DatabaseManager::class)->connection()->table('evidence_risk_review_logs')->first();
        self::assertNotNull($row);
        self::assertSame('tenant-x', $row->tenant_id, 'The resolver tenant must win over the client-supplied artifact tenant.');
    }

    private function bindTenant(?string $tenant): void
    {
        $this->app?->instance(TenantResolver::class, new class($tenant) implements TenantResolver
        {
            public function __construct(private readonly ?string $tenant) {}

            public function current(): ?string
            {
                return $this->tenant;
            }
        });
        // Rebuild the store + query so they pick up the freshly-bound resolver.
        $this->app?->forgetInstance(ReviewLogStore::class);
    }

    private function insertRow(string $reviewId, string $artifactId, ?string $tenant, string $maxVerdict, string $profile): void
    {
        $this->resolve(DatabaseManager::class)->connection()->table('evidence_risk_review_logs')->insert([
            'review_id' => $reviewId,
            'artifact_id' => $artifactId,
            'profile_key' => $profile,
            'tenant_id' => $tenant,
            'max_verdict' => $maxVerdict,
            'risk_score' => 0.5,
            'findings' => '[]',
            'claim_verdicts' => '{}',
            'source_tiers' => '{}',
            'budget' => '{}',
            'artifact' => '{}',
            'options' => '{}',
            'metadata' => '{}',
            'reviewed_at' => '2026-06-15 10:00:00',
            'created_at' => '2026-06-15 10:00:00',
        ]);
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
