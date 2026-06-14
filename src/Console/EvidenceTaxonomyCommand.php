<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Console;

use Illuminate\Console\Command;
use JsonException;
use Padosoft\EvidenceRiskReview\EvidenceRiskReview;

final class EvidenceTaxonomyCommand extends Command
{
    protected $signature = 'evidence:taxonomy {--pretty : Pretty-print JSON output.}';

    protected $description = 'Show evidence tiers, risk checks, verdicts, and claim assertiveness values as JSON.';

    public function __construct(
        private readonly EvidenceRiskReview $reviews,
    ) {
        parent::__construct();
    }

    /**
     * @throws JsonException
     */
    public function handle(): int
    {
        $this->line($this->json($this->reviews->taxonomy()));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function json(array $payload): string
    {
        $flags = $this->option('pretty') === true ? JSON_PRETTY_PRINT : 0;

        return json_encode($payload, JSON_THROW_ON_ERROR | $flags);
    }
}
