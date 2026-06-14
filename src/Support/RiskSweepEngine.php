<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Support;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Contracts\RiskCheck;
use Padosoft\EvidenceRiskReview\Contracts\RiskProfileContract;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewFinding;
use Padosoft\EvidenceRiskReview\Enums\RiskCostClass;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;

final class RiskSweepEngine
{
    /**
     * @var list<RiskCheck>
     */
    private array $checks;

    /**
     * @param  iterable<RiskCheck>  $checks
     */
    public function __construct(iterable $checks)
    {
        $this->checks = is_array($checks) ? array_values($checks) : iterator_to_array($checks, false);
        $this->assertNoDuplicateChecks($this->checks);
    }

    /**
     * @return list<ReviewFinding>
     */
    public function sweep(ReviewArtifact $artifact, RiskProfileContract $profile, BudgetMeter $meter): array
    {
        return $this->sweepCost($artifact, $profile, $meter, null);
    }

    /**
     * @return list<ReviewFinding>
     */
    public function sweepCheap(ReviewArtifact $artifact, RiskProfileContract $profile, BudgetMeter $meter): array
    {
        return $this->sweepCost($artifact, $profile, $meter, RiskCostClass::Cheap);
    }

    /**
     * @return list<ReviewFinding>
     */
    public function sweepHeavy(ReviewArtifact $artifact, RiskProfileContract $profile, BudgetMeter $meter): array
    {
        return $this->sweepCost($artifact, $profile, $meter, RiskCostClass::Heavy);
    }

    /**
     * @return list<ReviewFinding>
     */
    private function sweepCost(
        ReviewArtifact $artifact,
        RiskProfileContract $profile,
        BudgetMeter $meter,
        ?RiskCostClass $onlyCost,
    ): array {
        $findings = [];

        foreach ($this->supportedChecks($profile) as $check) {
            if ($onlyCost !== null && $check->costClass() !== $onlyCost) {
                continue;
            }

            if ($check->costClass() === RiskCostClass::Heavy && ! $meter->tryConsumeLlmCall()) {
                $findings[] = $this->skippedOverBudgetFinding($check);

                continue;
            }

            array_push($findings, ...$check->run($artifact, $profile, $meter));
        }

        return $findings;
    }

    /**
     * @param  list<ReviewFinding>  $findings
     * @return array<string, RiskVerdict>
     */
    public function reduceVerdicts(array $findings): array
    {
        $verdicts = [];

        foreach ($findings as $finding) {
            if ($finding->claimId === null) {
                continue;
            }

            $current = $verdicts[$finding->claimId] ?? RiskVerdict::Keep;

            if ($finding->verdict->severity() > $current->severity()) {
                $verdicts[$finding->claimId] = $finding->verdict;
            }
        }

        return $verdicts;
    }

    /**
     * @return list<RiskCheck>
     */
    private function supportedChecks(RiskProfileContract $profile): array
    {
        $checks = array_values(array_filter(
            $this->checks,
            static fn (RiskCheck $check): bool => $check->supports($profile) && $profile->enables($check->kind()),
        ));

        usort(
            $checks,
            static fn (RiskCheck $left, RiskCheck $right): int => self::costOrder($left) <=> self::costOrder($right),
        );

        return $checks;
    }

    private static function costOrder(RiskCheck $check): int
    {
        return $check->costClass() === RiskCostClass::Cheap ? 0 : 1;
    }

    private function skippedOverBudgetFinding(RiskCheck $check): ReviewFinding
    {
        return new ReviewFinding(
            checkKind: $check->kind()->value,
            claimId: null,
            verdict: RiskVerdict::FlagForHumanReview,
            reason: "Heavy check [{$check->kind()->value}] skipped because review budget is exhausted.",
            confidence: 1.0,
            costClass: 'skipped_over_budget',
        );
    }

    /**
     * @param  list<RiskCheck>  $checks
     */
    private function assertNoDuplicateChecks(array $checks): void
    {
        $seen = [];

        foreach ($checks as $check) {
            $key = $check->kind()->value.'|'.$check->costClass()->value;

            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Duplicate risk check registration [{$key}].");
            }

            $seen[$key] = true;
        }
    }
}
