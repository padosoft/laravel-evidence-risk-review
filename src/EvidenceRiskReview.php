<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview;

use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\Data\ReviewResult;
use Padosoft\EvidenceRiskReview\Data\SourceRef;
use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Profiles\DomainProfileRegistry;
use Padosoft\EvidenceRiskReview\Profiles\RiskProfileMetadata;
use Padosoft\EvidenceRiskReview\Support\ArrayData;
use Padosoft\EvidenceRiskReview\Support\EvidenceTierLabeler;
use Padosoft\EvidenceRiskReview\Support\ReviewEngine;
use Padosoft\EvidenceRiskReview\Support\TierResolver;
use Padosoft\EvidenceRiskReview\ValueObjects\EvidenceTierValue;

final readonly class EvidenceRiskReview
{
    public function __construct(
        private ReviewEngine $engine,
        private EvidenceTierLabeler $labeler,
        private DomainProfileRegistry $profiles,
        private TierResolver $tiers,
    ) {}

    public function review(ReviewArtifact $artifact, ?ReviewOptions $options = null): ReviewResult
    {
        return $this->engine->review($artifact, $options);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function reviewArray(array $payload): array
    {
        $artifactPayload = array_key_exists('artifact', $payload)
            ? ArrayData::requireMap($payload['artifact'], 'artifact')
            : $payload;
        $optionsPayload = array_key_exists('options', $payload)
            ? ArrayData::requireMap($payload['options'], 'options')
            : [];

        return $this->review(
            ReviewArtifact::fromArray($artifactPayload),
            ReviewOptions::fromArray($optionsPayload),
        )->toArray();
    }

    /**
     * @param  SourceRef|array<string, mixed>  $source
     */
    public function labelTier(SourceRef|array $source): EvidenceTierValue
    {
        return $this->labeler->labelSource($source instanceof SourceRef ? $source : SourceRef::fromArray($source));
    }

    /**
     * @return array<string, array{key: string, label: string, description: string, enabled_checks: list<string>}>
     */
    public function listProfiles(): array
    {
        return array_map(
            static fn (RiskProfileMetadata $profile): array => $profile->toArray(),
            $this->profiles->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function taxonomy(): array
    {
        return [
            'tiers' => array_map(
                static fn (EvidenceTierValue $tier): array => $tier->toArray(),
                $this->tiers->all(),
            ),
            'risk_checks' => array_map(
                static fn (RiskCheckKind $kind): string => $kind->value,
                RiskCheckKind::cases(),
            ),
            'risk_verdicts' => array_map(
                static fn (RiskVerdict $verdict): array => [
                    'key' => $verdict->value,
                    'severity' => $verdict->severity(),
                ],
                RiskVerdict::cases(),
            ),
            'claim_assertiveness' => array_map(
                static fn (ClaimAssertiveness $assertiveness): string => $assertiveness->value,
                ClaimAssertiveness::cases(),
            ),
        ];
    }
}
