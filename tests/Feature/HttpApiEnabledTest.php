<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Feature;

use Orchestra\Testbench\Attributes\WithConfig;
use Padosoft\EvidenceRiskReview\Contracts\EvidenceReviewerLlmContract;
use Padosoft\EvidenceRiskReview\Data\LlmRequest;
use Padosoft\EvidenceRiskReview\Data\LlmResponse;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\EvidenceRiskReview;
use Padosoft\EvidenceRiskReview\Exceptions\LlmUnavailableException;
use Padosoft\EvidenceRiskReview\Support\ReviewEngine;
use Padosoft\EvidenceRiskReview\Tests\TestCase;

#[WithConfig('evidence-risk-review.api.enabled', true, false)]
#[WithConfig('evidence-risk-review.review_log.store', 'array', false)]
final class HttpApiEnabledTest extends TestCase
{
    public function test_profiles_taxonomy_and_openapi_endpoints(): void
    {
        $this->getJson('/evidence-risk-review/api/profiles')
            ->assertOk()
            ->assertJsonPath('profiles.default.key', 'default');

        $this->getJson('/evidence-risk-review/api/profiles/default')
            ->assertOk()
            ->assertJsonPath('profile.key', 'default');

        $this->getJson('/evidence-risk-review/api/taxonomy')
            ->assertOk()
            ->assertJsonStructure(['tiers', 'risk_checks', 'risk_verdicts', 'claim_assertiveness']);

        $this->get('/evidence-risk-review/api/openapi.yaml')
            ->assertOk()
            ->assertSee('openapi: 3.1.0', false);
    }

    public function test_review_endpoint_creates_and_reads_logged_review(): void
    {
        $response = $this->postJson('/evidence-risk-review/api/reviews', [
            'artifact_id' => 'http-1',
            'answer_text' => 'No claims to check.',
        ])
            ->assertCreated()
            ->assertJsonPath('artifact_id', 'http-1');

        $reviewId = $response->json('review_id');
        self::assertIsString($reviewId);

        $this->getJson('/evidence-risk-review/api/reviews/'.$reviewId)
            ->assertOk()
            ->assertJsonPath('artifact_id', 'http-1');
    }

    public function test_review_endpoint_returns_flagged_result(): void
    {
        $response = $this->postJson('/evidence-risk-review/api/reviews', [
            'artifact_id' => 'http-flagged',
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
            'options' => ['dry_run' => true],
        ])
            ->assertCreated()
            ->assertJsonPath('artifact_id', 'http-flagged');

        self::assertNotSame([], $response->json('findings'));
    }

    public function test_http_error_contracts(): void
    {
        $this->getJson('/evidence-risk-review/api/profiles/missing')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'unknown_profile');

        $this->getJson('/evidence-risk-review/api/reviews/missing')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'unknown_review');

        $this->postJson('/evidence-risk-review/api/reviews', ['answer_text' => 'Missing id.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_error');

        $this->postJson('/evidence-risk-review/api/reviews', [
            'artifact_id' => 'unknown-profile',
            'answer_text' => 'No claims.',
            'options' => ['profile_key' => 'missing'],
        ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'unknown_profile');
    }

    public function test_llm_unavailable_maps_to_503(): void
    {
        config()->set('evidence-risk-review.llm.enabled', true);

        $this->app?->instance(EvidenceReviewerLlmContract::class, new class implements EvidenceReviewerLlmContract
        {
            public function complete(LlmRequest $request): LlmResponse
            {
                throw new LlmUnavailableException('LLM is offline.');
            }
        });
        $this->app?->forgetInstance(ReviewEngine::class);
        $this->app?->forgetInstance(EvidenceRiskReview::class);

        $this->postJson('/evidence-risk-review/api/reviews', [
            'artifact_id' => 'llm-down',
            'answer_text' => 'No claims.',
            'options' => ['label_via_llm' => true, 'dry_run' => true],
        ])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'llm_unavailable');
    }

    public function test_openapi_document_contains_required_paths(): void
    {
        $openapi = file_get_contents(__DIR__.'/../../resources/openapi.yaml');
        self::assertIsString($openapi);

        foreach (['/reviews:', '/reviews/{review}:', '/profiles:', '/profiles/{key}:', '/taxonomy:', '/openapi.yaml:'] as $path) {
            self::assertStringContainsString($path, $openapi);
        }
    }
}
