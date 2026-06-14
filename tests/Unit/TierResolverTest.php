<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Support\TierResolver;
use Padosoft\EvidenceRiskReview\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TierResolverTest extends TestCase
{
    #[Test]
    public function resolves_built_in_and_unknown_tiers_safely(): void
    {
        $resolver = $this->resolve(TierResolver::class);

        self::assertSame('official', $resolver->resolve(EvidenceTier::Official)->key);
        self::assertSame('unverified', $resolver->resolve('not_configured')->key);
        self::assertSame('unverified', $resolver->resolve(null)->key);
    }

    #[Test]
    public function overlays_rank_and_custom_tiers_from_config(): void
    {
        config()->set('evidence-risk-review.tiers.official', [
            'rank' => 88,
            'label' => 'Regulator',
        ]);
        config()->set('evidence-risk-review.tiers.case_law', [
            'rank' => 90,
            'label' => 'Case law',
        ]);

        $resolver = $this->resolve(TierResolver::class);

        self::assertSame(88, $resolver->resolve('official')->rank);
        self::assertSame('Regulator', $resolver->resolve('official')->label);
        self::assertSame('case_law', $resolver->resolve('case_law')->key);
        self::assertFalse($resolver->resolve('case_law')->builtin);

        $orderedKeys = array_keys($resolver->all());

        self::assertSame('guideline', $orderedKeys[0]);
        self::assertSame('case_law', $orderedKeys[1]);
        self::assertSame('official', $orderedKeys[2]);
    }

    #[Test]
    public function configured_resolution_fails_for_unknown_tier_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolve(TierResolver::class)->resolveConfigured('missing_tier');
    }
}
