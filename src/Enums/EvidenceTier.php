<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Enums;

enum EvidenceTier: string
{
    case Guideline = 'guideline';
    case PeerReviewed = 'peer_reviewed';
    case Official = 'official';
    case Preprint = 'preprint';
    case News = 'news';
    case Blog = 'blog';
    case SearchHint = 'search_hint';
    case Unverified = 'unverified';

    public function rank(): int
    {
        return match ($this) {
            self::Guideline => 100,
            self::PeerReviewed => 80,
            self::Official => 70,
            self::Preprint => 65,
            self::News => 45,
            self::Blog => 30,
            self::SearchHint => 15,
            self::Unverified => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Guideline => 'Guideline',
            self::PeerReviewed => 'Peer-reviewed',
            self::Official => 'Official source',
            self::Preprint => 'Preprint',
            self::News => 'News',
            self::Blog => 'Blog',
            self::SearchHint => 'Search hint',
            self::Unverified => 'Unverified',
        };
    }
}
