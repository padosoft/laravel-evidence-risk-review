<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Support;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Padosoft\EvidenceRiskReview\Contracts\EvidenceReviewerLlmContract;
use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use Padosoft\EvidenceRiskReview\Data\LlmRequest;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewFinding;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Data\ReviewResult;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Profiles\DomainProfileRegistry;
use Padosoft\EvidenceRiskReview\ValueObjects\EvidenceTierValue;
use Padosoft\EvidenceRiskReview\ValueObjects\ReviewBudget;

final readonly class ReviewEngine
{
    public function __construct(
        private DomainProfileRegistry $profiles,
        private RiskSweepEngine $sweep,
        private EvidenceTierLabeler $labeler,
        private TierResolver $tiers,
        private EvidenceReviewerLlmContract $llm,
        private ReviewLogStore $logStore,
        private ConfigRepository $config,
    ) {}

    public function review(ReviewArtifact $artifact, ?ReviewOptions $options = null): ReviewResult
    {
        $options ??= new ReviewOptions;
        $profile = $this->profiles->get($options->profileKey);
        $meter = new BudgetMeter($options->budget ?? $this->configuredBudget());
        $sourceTiers = $this->sourceTiers($artifact);
        $cheapFindings = $this->sweep->sweepCheap($artifact, $profile, $meter);
        $heavyFindings = $cheapFindings === [] || ! $this->llmEnabled()
            ? []
            : $this->sweep->sweepHeavy($artifact, $profile, $meter);

        if ($options->labelViaLlm && $this->llmEnabled()) {
            $sourceTiers = $this->refineSourceTiers($artifact, $sourceTiers, $meter);
        }

        $findings = [...$cheapFindings, ...$heavyFindings];
        $result = new ReviewResult(
            reviewId: $this->reviewId(),
            artifactId: $artifact->artifactId,
            profileKey: $profile->key(),
            findings: $findings,
            claimVerdicts: $this->sweep->reduceVerdicts($findings),
            sourceTiers: $sourceTiers,
            riskScore: $this->riskScore($findings),
            budget: $meter->consumed(),
            reviewedAt: new DateTimeImmutable,
            metadata: [
                'dry_run' => $options->dryRun,
                'heavy_checks_run' => $heavyFindings !== [],
                'llm_enabled' => $this->llmEnabled(),
            ],
        );

        if (! $options->dryRun) {
            $this->logStore->append($artifact, $options, $result);
        }

        return $result;
    }

    private function configuredBudget(): ReviewBudget
    {
        $budget = $this->config->get('evidence-risk-review.budget', []);

        return ReviewBudget::fromArray(is_array($budget) ? $budget : []);
    }

    private function llmEnabled(): bool
    {
        return $this->config->get('evidence-risk-review.llm.enabled', false) === true;
    }

    /**
     * @return array<string, EvidenceTierValue>
     */
    private function sourceTiers(ReviewArtifact $artifact): array
    {
        $tiers = [];

        foreach ($artifact->sources as $source) {
            $tiers[$source->id] = $this->labeler->labelSource($source);
        }

        return $tiers;
    }

    /**
     * @param  array<string, EvidenceTierValue>  $current
     * @return array<string, EvidenceTierValue>
     */
    private function refineSourceTiers(ReviewArtifact $artifact, array $current, BudgetMeter $meter): array
    {
        if (! $meter->tryConsumeLlmCall()) {
            return $current;
        }

        $response = $this->llm->complete(new LlmRequest(
            purpose: 'tier_refinement',
            prompt: 'Refine source evidence tiers only when the cheap label is ambiguous.',
            payload: ['artifact' => $artifact->toArray()],
            maxTokens: 1000,
        ));
        $meter->recordTokens($response->tokensUsed);

        $sourceTiers = $response->data['source_tiers'] ?? [];

        if (! is_array($sourceTiers)) {
            return $current;
        }

        foreach ($sourceTiers as $sourceId => $tier) {
            if (! is_string($sourceId) || ! is_string($tier)) {
                continue;
            }

            $current[$sourceId] = $this->tiers->resolveConfigured($tier);
        }

        return $current;
    }

    /**
     * @param  list<ReviewFinding>  $findings
     */
    private function riskScore(array $findings): float
    {
        $maxSeverity = 0;

        foreach ($findings as $finding) {
            $maxSeverity = max($maxSeverity, $finding->verdict->severity());
        }

        return $maxSeverity / RiskVerdict::Remove->severity();
    }

    private function reviewId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
