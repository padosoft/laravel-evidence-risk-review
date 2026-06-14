<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests\Unit;

use InvalidArgumentException;
use Padosoft\EvidenceRiskReview\Data\SourceRef;
use Padosoft\EvidenceRiskReview\Support\EvidenceTierLabeler;
use Padosoft\EvidenceRiskReview\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class EvidenceTierLabelerTest extends TestCase
{
    #[Test]
    public function declared_tier_wins_over_url_heuristics(): void
    {
        config()->set('evidence-risk-review.tiers.case_law', [
            'rank' => 90,
            'label' => 'Case law',
        ]);

        $source = new SourceRef(
            id: 'source-1',
            url: 'https://arxiv.org/abs/1234.56789',
            declaredTier: 'case_law',
        );

        self::assertSame('case_law', $this->labeler()->labelSource($source)->key);
    }

    #[Test]
    public function host_tier_hints_match_domains_as_literals(): void
    {
        config()->set('evidence-risk-review.tier_hints', [
            '*.nih.gov' => 'guideline',
            'exa.ple.org' => 'official',
        ]);

        self::assertSame(
            'guideline',
            $this->labeler()->labelSource(new SourceRef('source-1', 'https://pubs.nih.gov/path'))->key,
        );

        self::assertSame(
            'unverified',
            $this->labeler()->labelSource(new SourceRef('source-2', 'https://exaxple.org/path'))->key,
        );
    }

    #[Test]
    public function doi_signal_maps_to_peer_reviewed(): void
    {
        $source = new SourceRef(
            id: 'source-1',
            url: 'https://example.test/article',
            snippet: 'Published with DOI 10.1000/182.',
        );

        self::assertSame('peer_reviewed', $this->labeler()->labelSource($source)->key);
    }

    #[Test]
    public function invalid_matching_hint_tier_fails_loudly(): void
    {
        config()->set('evidence-risk-review.tier_hints', [
            'example.test' => 'not_configured',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->labeler()->labelSource(new SourceRef('source-1', 'https://example.test/path'));
    }

    #[Test]
    public function arxiv_domain_maps_to_preprint(): void
    {
        $source = new SourceRef('source-1', 'https://arxiv.org/abs/2401.00001');

        self::assertSame('preprint', $this->labeler()->labelSource($source)->key);
    }

    #[Test]
    public function arxiv_domain_stays_preprint_even_when_doi_is_present(): void
    {
        $source = new SourceRef(
            id: 'source-1',
            url: 'https://arxiv.org/abs/2401.00001',
            snippet: 'Later published with DOI 10.1000/182.',
        );

        self::assertSame('preprint', $this->labeler()->labelSource($source)->key);
    }

    #[Test]
    public function unknown_sources_fail_safe_to_unverified(): void
    {
        $source = new SourceRef('source-1', 'https://example.test/no-signal');

        self::assertSame('unverified', $this->labeler()->labelSource($source)->key);
    }

    private function labeler(): EvidenceTierLabeler
    {
        return $this->resolve(EvidenceTierLabeler::class);
    }
}
