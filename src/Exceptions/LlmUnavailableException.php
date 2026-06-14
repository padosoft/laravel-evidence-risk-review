<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Exceptions;

use RuntimeException;

final class LlmUnavailableException extends RuntimeException
{
    public static function disabled(): self
    {
        return new self('Evidence risk review LLM is not configured.');
    }
}
