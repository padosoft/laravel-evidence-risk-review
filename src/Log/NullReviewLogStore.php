<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Log;

use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Data\ReviewResult;

final class NullReviewLogStore implements ReviewLogStore
{
    public function append(ReviewArtifact $artifact, ReviewOptions $options, ReviewResult $result): void
    {
        //
    }
}
