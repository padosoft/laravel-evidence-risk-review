<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Exceptions;

use RuntimeException;

final class ProfileNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Risk profile [{$key}] is not registered.");
    }
}
