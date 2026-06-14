<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Live;

use Padosoft\EvidenceRiskReview\Tests\TestCase;

final class LiveSuiteTest extends TestCase
{
    public function test_live_suite_is_opt_in(): void
    {
        if (getenv('EVIDENCE_RISK_REVIEW_LIVE') !== '1') {
            self::markTestSkipped('Set EVIDENCE_RISK_REVIEW_LIVE=1 to run live evidence risk review checks.');
        }

        self::assertSame('1', getenv('EVIDENCE_RISK_REVIEW_LIVE'));
    }
}
