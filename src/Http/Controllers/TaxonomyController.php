<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Padosoft\EvidenceRiskReview\EvidenceRiskReview;

final readonly class TaxonomyController
{
    public function __construct(
        private EvidenceRiskReview $reviews,
    ) {}

    public function show(): JsonResponse
    {
        return new JsonResponse($this->reviews->taxonomy());
    }
}
