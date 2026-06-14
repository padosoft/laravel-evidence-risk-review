<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Checks;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Contracts\RiskCheck;
use Padosoft\EvidenceRiskReview\Contracts\RiskProfileContract;
use Padosoft\EvidenceRiskReview\Data\ClaimRef;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewFinding;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskCostClass;
use Padosoft\EvidenceRiskReview\Support\BudgetMeter;

abstract class KeywordRiskCheck implements RiskCheck
{
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
        $keywords = $this->keywords($profile);

        if ($keywords === []) {
            return [];
        }

        $findings = [];

        foreach ($artifact->claims as $claim) {
            $matched = $this->matchedKeyword($claim, $keywords);

            if ($matched === null) {
                continue;
            }

            $findings[] = new ReviewFinding(
                checkKind: $this->kind()->value,
                claimId: $claim->id,
                verdict: $profile->verdictFor($this->kind()),
                reason: "Claim matched {$this->kind()->value} keyword [{$matched}].",
                confidence: 0.7,
                costClass: RiskCostClass::Cheap->value,
                evidence: ['keyword' => $matched],
            );
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function keywords(RiskProfileContract $profile): array
    {
        $settings = $profile->settingsFor($this->kind());

        if (! array_key_exists('keywords', $settings)) {
            throw new InvalidArgumentException("Risk check [{$this->kind()->value}] keywords must be configured.");
        }

        $keywords = $settings['keywords'];

        if (! is_array($keywords) || ! array_is_list($keywords)) {
            throw new InvalidArgumentException("Risk check [{$this->kind()->value}] keywords must be a list.");
        }

        $normalized = [];

        foreach ($keywords as $keyword) {
            if (! is_string($keyword) || $keyword === '') {
                throw new InvalidArgumentException("Risk check [{$this->kind()->value}] keywords must contain non-empty strings.");
            }

            $normalized[] = mb_strtolower($keyword);
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $keywords
     */
    private function matchedKeyword(ClaimRef $claim, array $keywords): ?string
    {
        $text = mb_strtolower($claim->text);

        foreach ($keywords as $keyword) {
            if ($this->keywordMatches($text, $keyword)) {
                return $keyword;
            }
        }

        return null;
    }

    private function keywordMatches(string $text, string $keyword): bool
    {
        $pattern = '/(?<![\pL\pN_])'.preg_quote($keyword, '/').'(?![\pL\pN_])/u';

        return preg_match($pattern, $text) === 1;
    }

    abstract public function kind(): RiskCheckKind;
}
