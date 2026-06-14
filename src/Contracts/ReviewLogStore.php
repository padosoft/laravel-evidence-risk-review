<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Contracts;

use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Data\ReviewResult;

interface ReviewLogStore
{
    public function append(ReviewArtifact $artifact, ReviewOptions $options, ReviewResult $result): void;
}
