<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Padosoft\EvidenceRiskReview\EvidenceRiskReview;
use Padosoft\EvidenceRiskReview\Http\Responses\ErrorResponse;

final readonly class ProfileController
{
    public function __construct(
        private EvidenceRiskReview $reviews,
    ) {}

    public function index(): JsonResponse
    {
        return new JsonResponse(['profiles' => $this->reviews->listProfiles()]);
    }

    public function show(string $key): JsonResponse
    {
        $profiles = $this->reviews->listProfiles();

        if (! isset($profiles[$key])) {
            return ErrorResponse::make('unknown_profile', "Profile [{$key}] was not found.", 404);
        }

        return new JsonResponse(['profile' => $profiles[$key]]);
    }
}
