<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Http\Controllers;

use Illuminate\Http\Response;

final class OpenApiController
{
    public function show(): Response
    {
        $yaml = file_get_contents(__DIR__.'/../../../resources/openapi.yaml');

        return new Response(is_string($yaml) ? $yaml : '', 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}
