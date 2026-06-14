<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnumsTest extends TestCase
{
    #[Test]
    public function evidence_tiers_have_expected_default_ranks(): void
    {
        self::assertSame(100, EvidenceTier::Guideline->rank());
        self::assertSame(80, EvidenceTier::PeerReviewed->rank());
        self::assertSame(70, EvidenceTier::Official->rank());
        self::assertSame(65, EvidenceTier::Preprint->rank());
        self::assertSame(0, EvidenceTier::Unverified->rank());
    }

    #[Test]
    public function risk_verdicts_preserve_precedence_order(): void
    {
        self::assertGreaterThan(RiskVerdict::FlagForHumanReview->severity(), RiskVerdict::Remove->severity());
        self::assertGreaterThan(RiskVerdict::Soften->severity(), RiskVerdict::FlagForHumanReview->severity());
        self::assertGreaterThan(RiskVerdict::Keep->severity(), RiskVerdict::Soften->severity());
    }
}
