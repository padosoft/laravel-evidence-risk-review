<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Feature;

use Padosoft\EvidenceRiskReview\Tests\TestCase;

final class HttpApiDefaultOffTest extends TestCase
{
    public function test_http_api_routes_are_not_registered_by_default(): void
    {
        $this->getJson('/evidence-risk-review/api/taxonomy')
            ->assertNotFound();
    }
}
