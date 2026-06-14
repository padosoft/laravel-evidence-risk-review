<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Facades;

use Illuminate\Support\Facades\Facade;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Data\ReviewResult;
use Padosoft\EvidenceRiskReview\Data\SourceRef;
use Padosoft\EvidenceRiskReview\ValueObjects\EvidenceTierValue;

/**
 * @method static ReviewResult review(ReviewArtifact $artifact, ?ReviewOptions $options = null)
 * @method static array<string, mixed> reviewArray(array<string, mixed> $payload)
 * @method static EvidenceTierValue labelTier(SourceRef|array<string, mixed> $source)
 * @method static array<string, array{key: string, label: string, description: string, enabled_checks: list<string>}> listProfiles()
 * @method static array<string, mixed> taxonomy()
 */
final class EvidenceRiskReview extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'evidence-risk-review';
    }
}
