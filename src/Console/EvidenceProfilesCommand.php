<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Console;

use Illuminate\Console\Command;
use JsonException;
use Padosoft\EvidenceRiskReview\EvidenceRiskReview;

final class EvidenceProfilesCommand extends Command
{
    protected $signature = 'evidence:profiles {--pretty : Pretty-print JSON output.}';

    protected $description = 'List configured evidence risk review profiles as JSON.';

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
        $this->line($this->json(['profiles' => $this->reviews->listProfiles()]));

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
