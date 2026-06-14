<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Checks;

use Padosoft\EvidenceRiskReview\Contracts\EvidenceReviewerLlmContract;
use Padosoft\EvidenceRiskReview\Contracts\RiskCheck;
use Padosoft\EvidenceRiskReview\Contracts\RiskProfileContract;
use Padosoft\EvidenceRiskReview\Data\LlmRequest;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewFinding;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskCostClass;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Support\BudgetMeter;

final class LlmEvidenceStrengthCheck implements RiskCheck
{
    public function __construct(
        private readonly EvidenceReviewerLlmContract $llm,
    ) {}

    public function kind(): RiskCheckKind
    {
        return RiskCheckKind::EvidenceStrength;
    }

    public function costClass(): RiskCostClass
    {
        return RiskCostClass::Heavy;
    }

    public function supports(RiskProfileContract $profile): bool
    {
        return $profile->enables($this->kind());
    }

    public function run(ReviewArtifact $artifact, RiskProfileContract $profile, BudgetMeter $meter): array
    {
        $response = $this->llm->complete(new LlmRequest(
            purpose: 'risk_check.evidence_strength',
            prompt: 'Review claims that already failed cheap evidence checks and return conservative risk findings.',
            payload: [
                'artifact' => $artifact->toArray(),
                'profile' => $profile->key(),
            ],
            maxTokens: 1000,
        ));
        $meter->recordTokens($response->tokensUsed);

        return $this->findings($response->data['findings'] ?? []);
    }

    /**
     * @return list<ReviewFinding>
     */
    private function findings(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $findings = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $claimId = $item['claim_id'] ?? null;
            $reason = $item['reason'] ?? null;
            $verdict = $item['verdict'] ?? RiskVerdict::FlagForHumanReview->value;

            if (($claimId !== null && ! is_string($claimId)) || ! is_string($reason) || ! is_string($verdict)) {
                continue;
            }

            $findings[] = new ReviewFinding(
                checkKind: $this->kind()->value,
                claimId: $claimId,
                verdict: RiskVerdict::from($verdict),
                reason: $reason,
                suggestedRewrite: is_string($item['suggested_rewrite'] ?? null) ? $item['suggested_rewrite'] : null,
                confidence: is_float($item['confidence'] ?? null) ? $item['confidence'] : 0.75,
                costClass: RiskCostClass::Heavy->value,
            );
        }

        return $findings;
    }
}
