<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\ValueObjects\EvidenceTierValue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EvidenceTierValueTest extends TestCase
{
    #[Test]
    public function built_in_tier_value_uses_enum_metadata(): void
    {
        $value = EvidenceTierValue::fromBuiltIn(EvidenceTier::Official);

        self::assertSame([
            'key' => 'official',
            'rank' => 70,
            'label' => 'Official source',
            'builtin' => true,
        ], $value->toArray());
    }

    #[Test]
    public function rejects_invalid_rank(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EvidenceTierValue('too_high', 101, 'Too high');
    }
}
