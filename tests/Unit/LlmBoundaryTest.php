<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Data\LlmRequest;
use Padosoft\EvidenceRiskReview\Data\LlmResponse;
use Padosoft\EvidenceRiskReview\Llm\CallbackEvidenceReviewerLlm;
use Padosoft\EvidenceRiskReview\Llm\NullEvidenceReviewerLlm;
use Padosoft\EvidenceRiskReview\Tests\TestCase;

final class LlmBoundaryTest extends TestCase
{
    public function test_null_llm_returns_empty_structured_response(): void
    {
        $response = (new NullEvidenceReviewerLlm)->complete(new LlmRequest('test', 'Prompt'));

        self::assertSame('', $response->text);
        self::assertSame(['findings' => [], 'source_tiers' => []], $response->data);
        self::assertSame(0, $response->tokensUsed);
    }

    public function test_callback_llm_receives_request_and_must_return_response(): void
    {
        $seen = null;
        $llm = new CallbackEvidenceReviewerLlm(function (LlmRequest $request) use (&$seen): LlmResponse {
            $seen = $request->toArray();

            return new LlmResponse(text: 'ok', data: ['answer' => true], tokensUsed: 7);
        });

        $response = $llm->complete(new LlmRequest('purpose', 'Prompt', ['id' => 'a'], 123));

        self::assertSame([
            'purpose' => 'purpose',
            'prompt' => 'Prompt',
            'payload' => ['id' => 'a'],
            'max_tokens' => 123,
        ], $seen);
        self::assertSame('ok', $response->text);
        self::assertSame(7, $response->tokensUsed);
    }

    public function test_callback_llm_rejects_invalid_callback_result(): void
    {
        $llm = new CallbackEvidenceReviewerLlm(static fn (): string => 'nope');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('LLM callback must return an LlmResponse.');

        $llm->complete(new LlmRequest('purpose', 'Prompt'));
    }
}
