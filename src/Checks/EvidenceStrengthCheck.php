<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Checks;

use Padosoft\EvidenceRiskReview\Contracts\RiskCheck;
use Padosoft\EvidenceRiskReview\Contracts\RiskProfileContract;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewFinding;
use Padosoft\EvidenceRiskReview\Data\SourceRef;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskCostClass;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Support\BudgetMeter;
use Padosoft\EvidenceRiskReview\Support\EvidenceTierLabeler;
use Padosoft\EvidenceRiskReview\Support\TierResolver;

final class EvidenceStrengthCheck implements RiskCheck
{
    public function __construct(
        private readonly EvidenceTierLabeler $labeler,
        private readonly TierResolver $tiers,
    ) {}

    public function kind(): RiskCheckKind
    {
        return RiskCheckKind::EvidenceStrength;
    }

    public function costClass(): RiskCostClass
    {
        return RiskCostClass::Cheap;
    }

    public function supports(RiskProfileContract $profile): bool
    {
        return $profile->enables($this->kind());
    }

    public function run(ReviewArtifact $artifact, RiskProfileContract $profile, BudgetMeter $meter): array
    {
        $sources = $this->sourcesById($artifact->sources);
        $findings = [];

        foreach ($artifact->claims as $claim) {
            $required = $this->tiers->resolveConfigured($profile->minimumTierFor($claim->assertiveness));
            $best = $this->tiers->unverified();

            foreach ($claim->sourceIds as $sourceId) {
                if (! isset($sources[$sourceId])) {
                    continue;
                }

                $tier = $this->labeler->labelSource($sources[$sourceId]);

                if ($tier->rank > $best->rank) {
                    $best = $tier;
                }
            }

            if ($best->rank >= $required->rank) {
                continue;
            }

            $verdict = $profile->verdictFor($this->kind());

            $findings[] = new ReviewFinding(
                checkKind: $this->kind()->value,
                claimId: $claim->id,
                verdict: $verdict === RiskVerdict::Keep
                    ? RiskVerdict::Soften
                    : $verdict,
                reason: "Claim requires evidence tier [{$required->key}] but best source tier is [{$best->key}].",
                suggestedRewrite: 'Soften the claim or add stronger supporting evidence.',
                confidence: 0.85,
                costClass: RiskCostClass::Cheap->value,
                evidence: [
                    'required_tier' => $required->key,
                    'best_tier' => $best->key,
                    'source_ids' => $claim->sourceIds,
                ],
            );
        }

        return $findings;
    }

    /**
     * @param  list<SourceRef>  $sources
     * @return array<string, SourceRef>
     */
    private function sourcesById(array $sources): array
    {
        $indexed = [];

        foreach ($sources as $source) {
            $indexed[$source->id] = $source;
        }

        return $indexed;
    }
}
