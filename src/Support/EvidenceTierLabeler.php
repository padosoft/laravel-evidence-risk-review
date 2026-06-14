<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Data\SourceRef;
use Padosoft\EvidenceRiskReview\Enums\EvidenceTier;
use Padosoft\EvidenceRiskReview\ValueObjects\EvidenceTierValue;

final class EvidenceTierLabeler
{
    public function __construct(
        private readonly TierResolver $tiers,
        private readonly ConfigRepository $config,
    ) {}

    public function labelSource(SourceRef $source): EvidenceTierValue
    {
        if ($source->declaredTier !== null) {
            return $this->tiers->resolve($source->declaredTier);
        }

        $host = $this->hostFromUrl($source->url);

        if ($host !== null) {
            $hinted = $this->tierFromHints($host);

            if ($hinted !== null) {
                return $hinted;
            }
        }

        if ($host !== null && $this->matchesDomainPattern($host, 'arxiv.org')) {
            return $this->tiers->resolve(EvidenceTier::Preprint);
        }

        if ($this->hasDoiSignal($source)) {
            return $this->tiers->resolve(EvidenceTier::PeerReviewed);
        }

        return $this->tiers->unverified();
    }

    private function hostFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower($host);
    }

    private function tierFromHints(string $host): ?EvidenceTierValue
    {
        $hints = $this->config->get('evidence-risk-review.tier_hints', []);

        if (! is_array($hints)) {
            throw new InvalidArgumentException('Configured tier hints must be an array.');
        }

        foreach ($hints as $domain => $tier) {
            if (! is_string($domain) || ! is_string($tier)) {
                throw new InvalidArgumentException('Tier hints must map domain strings to tier strings.');
            }

            if ($this->matchesDomainPattern($host, $domain)) {
                return $this->tiers->resolveConfigured($tier);
            }
        }

        return null;
    }

    private function matchesDomainPattern(string $host, string $pattern): bool
    {
        $normalized = strtolower(trim($pattern));

        if ($normalized === '') {
            return false;
        }

        if (str_starts_with($normalized, '*.')) {
            $domain = substr($normalized, 2);

            return $host === $domain || str_ends_with($host, '.'.$domain);
        }

        return $host === $normalized || str_ends_with($host, '.'.$normalized);
    }

    private function hasDoiSignal(SourceRef $source): bool
    {
        $haystack = implode(' ', array_filter([
            $source->url,
            $source->title,
            $source->snippet,
            $this->metadataString($source->metadata, 'doi'),
        ]));

        return preg_match('/\b10\.\d{4,9}\/[-._;()\/:A-Z0-9]+\b/i', $haystack) === 1;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function metadataString(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
