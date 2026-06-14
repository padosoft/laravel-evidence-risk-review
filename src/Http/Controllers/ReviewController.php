<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\EvidenceRiskReview;
use Padosoft\EvidenceRiskReview\Exceptions\LlmUnavailableException;
use Padosoft\EvidenceRiskReview\Exceptions\ProfileNotFoundException;
use Padosoft\EvidenceRiskReview\Http\Requests\ReviewPayloadRequest;
use Padosoft\EvidenceRiskReview\Http\Responses\ErrorResponse;
use Padosoft\EvidenceRiskReview\Log\ReviewLogQuery;
use Throwable;
use ValueError;

final readonly class ReviewController
{
    public function __construct(
        private EvidenceRiskReview $reviews,
        private ReviewPayloadRequest $payloads,
        private ReviewLogQuery $logs,
    ) {}

    public function store(Request $request): JsonResponse
    {
        try {
            return new JsonResponse(
                $this->reviews->reviewArray($this->payloads->payload($request)),
                201,
            );
        } catch (ProfileNotFoundException $exception) {
            return ErrorResponse::make('unknown_profile', $exception->getMessage(), 404);
        } catch (LlmUnavailableException $exception) {
            return ErrorResponse::make('llm_unavailable', $exception->getMessage(), 503);
        } catch (InvalidArgumentException|ValueError $exception) {
            return ErrorResponse::make('validation_error', $exception->getMessage(), 422);
        } catch (Throwable $exception) {
            return ErrorResponse::make('internal_error', $exception->getMessage(), 500);
        }
    }

    public function show(string $review): JsonResponse
    {
        $result = $this->logs->find($review);

        if ($result === null) {
            return ErrorResponse::make('unknown_review', "Review [{$review}] was not found.", 404);
        }

        return new JsonResponse($result);
    }
}
