<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Http\Requests;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Support\ArrayData;

final class ReviewPayloadRequest
{
    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->request->all();
        }

        if (! is_array($payload)) {
            throw new InvalidArgumentException('Expected JSON object payload.');
        }

        return ArrayData::requireMap($payload, 'request');
    }
}
