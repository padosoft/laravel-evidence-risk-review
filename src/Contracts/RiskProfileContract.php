<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Contracts;

use Padosoft\EvidenceRiskReview\Enums\ClaimAssertiveness;
use Padosoft\EvidenceRiskReview\Enums\RiskCheckKind;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;

interface RiskProfileContract
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /**
     * @return list<RiskCheckKind>
     */
    public function enabledChecks(): array;

    public function enables(RiskCheckKind $kind): bool;

    public function minimumTierFor(ClaimAssertiveness $assertiveness): string;

    /**
     * @return array<string, mixed>
     */
    public function settingsFor(RiskCheckKind $kind): array;

    public function verdictFor(RiskCheckKind $kind): RiskVerdict;
}
