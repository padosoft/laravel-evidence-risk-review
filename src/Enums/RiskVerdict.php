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

    /**
     * The highest-severity verdict among the given verdicts (string values or
     * cases). Defaults to {@see self::Keep} when the list is empty. Used to
     * derive a review's overall `max_verdict` for the log list + filtering.
     *
     * @param  iterable<RiskVerdict|string>  $verdicts
     */
    public static function highest(iterable $verdicts): self
    {
        $max = self::Keep;

        foreach ($verdicts as $verdict) {
            $case = $verdict instanceof self ? $verdict : self::tryFrom((string) $verdict);

            if ($case !== null && $case->severity() > $max->severity()) {
                $max = $case;
            }
        }

        return $max;
    }

    /**
     * All verdicts whose severity is >= this one — the set used to translate a
     * `min_verdict` filter into a queryable allow-list.
     *
     * @return list<string>
     */
    public static function atLeast(self $minimum): array
    {
        $allowed = [];

        foreach (self::cases() as $case) {
            if ($case->severity() >= $minimum->severity()) {
                $allowed[] = $case->value;
            }
        }

        return $allowed;
    }
}
