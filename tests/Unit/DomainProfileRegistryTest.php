<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Exceptions\ProfileNotFoundException;
use Padosoft\EvidenceRiskReview\Profiles\ConfigRiskProfile;
use Padosoft\EvidenceRiskReview\Profiles\DomainProfileRegistry;
use Padosoft\EvidenceRiskReview\Tests\TestCase;

final class DomainProfileRegistryTest extends TestCase
{
    public function test_it_loads_builtin_profiles_from_config(): void
    {
        $registry = $this->resolve(DomainProfileRegistry::class);

        self::assertSame(['default', 'engineering', 'finance', 'legal', 'medical'], array_keys($registry->all()));
        self::assertTrue($registry->has('medical'));
        self::assertSame('default', $registry->default()->key());
        self::assertSame(EvidenceTier::PeerReviewed->value, $registry->get('default')->minimumTierFor(ClaimAssertiveness::Definitive));
        self::assertTrue($registry->get('engineering')->enables(RiskCheckKind::BoundaryCondition));
        self::assertSame(RiskVerdict::Remove, $registry->get('medical')->verdictFor(RiskCheckKind::Contraindication));
    }

    public function test_unknown_profile_fails_loudly(): void
    {
        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage('Risk profile [missing] is not registered.');

        $this->resolve(DomainProfileRegistry::class)->get('missing');
    }

    public function test_profile_config_rejects_bad_shapes_and_explicit_null_checks(): void
    {
        $payload = [
            'label' => 'Broken',
            'description' => 'Broken profile',
            'enabled_checks' => [RiskCheckKind::EvidenceStrength->value],
            'min_tier' => [ClaimAssertiveness::Likely->value => EvidenceTier::Official->value],
            'checks' => null,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile [broken] checks must be a map.');

        ConfigRiskProfile::fromArray('broken', $payload);
    }

    public function test_profile_config_requires_every_assertiveness_minimum_tier(): void
    {
        $payload = [
            'label' => 'Broken',
            'description' => 'Broken profile',
            'enabled_checks' => [RiskCheckKind::EvidenceStrength->value],
            'min_tier' => [
                ClaimAssertiveness::Likely->value => EvidenceTier::Official->value,
                ClaimAssertiveness::Tentative->value => EvidenceTier::SearchHint->value,
            ],
            'checks' => [],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile [broken] min_tier missing [definitive].');

        ConfigRiskProfile::fromArray('broken', $payload);
    }

    public function test_profile_config_rejects_malformed_keyword_settings(): void
    {
        $payload = [
            'label' => 'Broken',
            'description' => 'Broken profile',
            'enabled_checks' => [RiskCheckKind::RedFlag->value],
            'min_tier' => [
                ClaimAssertiveness::Definitive->value => EvidenceTier::PeerReviewed->value,
                ClaimAssertiveness::Likely->value => EvidenceTier::Official->value,
                ClaimAssertiveness::Tentative->value => EvidenceTier::SearchHint->value,
            ],
            'checks' => [
                RiskCheckKind::RedFlag->value => [
                    'keywords' => ['first' => 'fraud'],
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected keyword list at [profiles.broken.checks.red_flag.keywords].');

        ConfigRiskProfile::fromArray('broken', $payload);
    }

    public function test_profile_config_rejects_explicit_null_verdict_overrides(): void
    {
        $profile = ConfigRiskProfile::fromArray('broken', [
            'label' => 'Broken',
            'description' => 'Broken profile',
            'enabled_checks' => [RiskCheckKind::RedFlag->value],
            'min_tier' => [
                ClaimAssertiveness::Definitive->value => EvidenceTier::PeerReviewed->value,
                ClaimAssertiveness::Likely->value => EvidenceTier::Official->value,
                ClaimAssertiveness::Tentative->value => EvidenceTier::SearchHint->value,
            ],
            'checks' => [
                RiskCheckKind::RedFlag->value => [
                    'verdict' => null,
                    'keywords' => ['fraud'],
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile [broken] verdict for [red_flag] must be a string.');

        $profile->verdictFor(RiskCheckKind::RedFlag);
    }

    public function test_profile_config_rejects_unknown_check_settings_keys(): void
    {
        $payload = [
            'label' => 'Broken',
            'description' => 'Broken profile',
            'enabled_checks' => [RiskCheckKind::RedFlag->value],
            'min_tier' => [
                ClaimAssertiveness::Definitive->value => EvidenceTier::PeerReviewed->value,
                ClaimAssertiveness::Likely->value => EvidenceTier::Official->value,
                ClaimAssertiveness::Tentative->value => EvidenceTier::SearchHint->value,
            ],
            'checks' => [
                'red_flags' => [
                    'keywords' => ['fraud'],
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown risk check settings key [profiles.broken.checks.red_flags].');

        ConfigRiskProfile::fromArray('broken', $payload);
    }

    public function test_profile_config_requires_keywords_for_enabled_keyword_checks(): void
    {
        $payload = [
            'label' => 'Broken',
            'description' => 'Broken profile',
            'enabled_checks' => [RiskCheckKind::RedFlag->value],
            'min_tier' => [
                ClaimAssertiveness::Definitive->value => EvidenceTier::PeerReviewed->value,
                ClaimAssertiveness::Likely->value => EvidenceTier::Official->value,
                ClaimAssertiveness::Tentative->value => EvidenceTier::SearchHint->value,
            ],
            'checks' => [
                RiskCheckKind::RedFlag->value => [
                    'verdict' => RiskVerdict::Remove->value,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile [broken] check [red_flag] must define keywords.');

        ConfigRiskProfile::fromArray('broken', $payload);
    }

    public function test_default_profile_key_must_be_a_string(): void
    {
        config()->set('evidence-risk-review.default_profile', 123);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Default profile key must be a string.');

        $this->resolve(DomainProfileRegistry::class)->default();
    }
}
