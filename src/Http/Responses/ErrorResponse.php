<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ErrorResponse
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function make(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}
