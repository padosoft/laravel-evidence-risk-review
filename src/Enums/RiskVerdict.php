<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Enums;

enum RiskVerdict: string
{
    case Keep = 'keep';
    case Soften = 'soften';
    case FlagForHumanReview = 'flag_for_human_review';
    case Remove = 'remove';

    public function severity(): int
    {
        return match ($this) {
            self::Keep => 0,
            self::Soften => 1,
            self::FlagForHumanReview => 2,
            self::Remove => 3,
        };
    }
}
