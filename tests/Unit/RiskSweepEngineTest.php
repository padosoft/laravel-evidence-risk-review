<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Checks\OverGeneralizationCheck;
use Padosoft\EvidenceRiskReview\Contracts\RiskCheck;
use Padosoft\EvidenceRiskReview\Contracts\RiskProfileContract;
use Padosoft\EvidenceRiskReview\Data\ClaimRef;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewFinding;
use Padosoft\EvidenceRiskReview\Data\SourceRef;
use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskCostClass;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Profiles\DomainProfileRegistry;
use Padosoft\EvidenceRiskReview\Support\BudgetMeter;
use Padosoft\EvidenceRiskReview\Support\RiskSweepEngine;
use Padosoft\EvidenceRiskReview\Tests\TestCase;
use Padosoft\EvidenceRiskReview\ValueObjects\ReviewBudget;

final class RiskSweepEngineTest extends TestCase
{
    public function test_builtin_engine_runs_profile_enabled_checks(): void
    {
        $engine = $this->resolve(RiskSweepEngine::class);
        $profile = $this->resolve(DomainProfileRegistry::class)->get('default');
        $artifact = new ReviewArtifact(
            artifactId: 'artifact-1',
            answerText: 'Review answer',
            claims: [
                new ClaimRef('c1', 'This always proves the outcome.', ClaimAssertiveness::Likely, ['blog']),
                new ClaimRef('c2', 'It is safe for children.', ClaimAssertiveness::Likely, ['blog']),
                new ClaimRef('c3', 'This is contraindicated with that drug.', ClaimAssertiveness::Likely, ['blog']),
                new ClaimRef('c4', 'It works in all cases.', ClaimAssertiveness::Likely, ['blog']),
                new ClaimRef('c5', 'This is an emergency fraud scenario.', ClaimAssertiveness::Likely, ['blog']),
            ],
            sources: [
                new SourceRef('blog', declaredTier: EvidenceTier::Blog->value),
            ],
        );

        $findings = $engine->sweep($artifact, $profile, new BudgetMeter(new ReviewBudget));
        $kinds = array_values(array_unique(array_map(
            static fn (ReviewFinding $finding): string => $finding->checkKind,
            $findings,
        )));
        sort($kinds);

        self::assertSame([
            RiskCheckKind::BoundaryCondition->value,
            RiskCheckKind::Contraindication->value,
            RiskCheckKind::EvidenceStrength->value,
            RiskCheckKind::OverGeneralization->value,
            RiskCheckKind::RedFlag->value,
            RiskCheckKind::SpecialPopulation->value,
        ], $kinds);
        self::assertContains(RiskVerdict::Remove, array_map(
            static fn (ReviewFinding $finding): RiskVerdict => $finding->verdict,
            $findings,
        ));
    }

    public function test_it_reduces_claim_verdicts_by_highest_severity(): void
    {
        $engine = new RiskSweepEngine([]);

        $verdicts = $engine->reduceVerdicts([
            new ReviewFinding('a', 'c1', RiskVerdict::Soften, 'soften'),
            new ReviewFinding('b', 'c1', RiskVerdict::Remove, 'remove'),
            new ReviewFinding('c', 'c2', RiskVerdict::FlagForHumanReview, 'flag'),
            new ReviewFinding('d', null, RiskVerdict::Remove, 'global'),
        ]);

        self::assertSame(RiskVerdict::Remove, $verdicts['c1']);
        self::assertSame(RiskVerdict::FlagForHumanReview, $verdicts['c2']);
        self::assertArrayNotHasKey('', $verdicts);
    }

    public function test_keyword_checks_match_word_boundaries_not_substrings(): void
    {
        $profile = $this->resolve(DomainProfileRegistry::class)->get('default');
        $engine = new RiskSweepEngine([new OverGeneralizationCheck]);

        $safeFindings = $engine->sweep(
            new ReviewArtifact(
                artifactId: 'artifact-1',
                answerText: 'Review answer',
                claims: [new ClaimRef('c1', 'This improves latency.', ClaimAssertiveness::Likely)],
            ),
            $profile,
            new BudgetMeter(new ReviewBudget),
        );
        $riskyFindings = $engine->sweep(
            new ReviewArtifact(
                artifactId: 'artifact-2',
                answerText: 'Review answer',
                claims: [new ClaimRef('c1', 'This proves latency.', ClaimAssertiveness::Likely)],
            ),
            $profile,
            new BudgetMeter(new ReviewBudget),
        );

        self::assertSame([], $safeFindings);
        self::assertSame(RiskCheckKind::OverGeneralization->value, $riskyFindings[0]->checkKind);
    }

    public function test_cheap_checks_run_before_heavy_checks_and_budget_can_skip_heavy(): void
    {
        RecordingRiskCheck::$events = [];
        $profile = $this->resolve(DomainProfileRegistry::class)->get('default');
        $artifact = new ReviewArtifact('artifact-1', 'answer');
        $engine = new RiskSweepEngine([
            new RecordingRiskCheck('heavy', RiskCheckKind::RedFlag, RiskCostClass::Heavy),
            new RecordingRiskCheck('cheap', RiskCheckKind::EvidenceStrength, RiskCostClass::Cheap),
        ]);

        $findings = $engine->sweep($artifact, $profile, new BudgetMeter(new ReviewBudget(maxLlmCalls: 1, maxTokens: 10, maxHeavyChecks: 1)));

        self::assertSame(['cheap', 'heavy'], RecordingRiskCheck::$events);
        self::assertCount(2, $findings);

        RecordingRiskCheck::$events = [];
        $skipped = $engine->sweep($artifact, $profile, new BudgetMeter(new ReviewBudget(maxLlmCalls: 0, maxTokens: 10, maxHeavyChecks: 1)));

        self::assertSame(['cheap'], RecordingRiskCheck::$events);
        self::assertSame('skipped_over_budget', $skipped[1]->costClass);
    }

    public function test_duplicate_check_registration_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate risk check registration [red_flag|cheap].');

        new RiskSweepEngine([
            new RecordingRiskCheck('a', RiskCheckKind::RedFlag, RiskCostClass::Cheap),
            new RecordingRiskCheck('b', RiskCheckKind::RedFlag, RiskCostClass::Cheap),
        ]);
    }
}

final class RecordingRiskCheck implements RiskCheck
{
    /**
     * @var list<string>
     */
    public static array $events = [];

    public function __construct(
        private readonly string $name,
        private readonly RiskCheckKind $kind,
        private readonly RiskCostClass $costClass,
    ) {}

    public function kind(): RiskCheckKind
    {
        return $this->kind;
    }

    public function costClass(): RiskCostClass
    {
        return $this->costClass;
    }

    public function supports(RiskProfileContract $profile): bool
    {
        return true;
    }

    public function run(ReviewArtifact $artifact, RiskProfileContract $profile, BudgetMeter $meter): array
    {
        self::$events[] = $this->name;

        return [
            new ReviewFinding($this->kind->value, null, RiskVerdict::Keep, $this->name, costClass: $this->costClass->value),
        ];
    }
}
