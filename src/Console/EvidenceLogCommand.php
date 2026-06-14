<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use JsonException;
use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use Padosoft\EvidenceRiskReview\Log\ArrayReviewLogStore;
use Padosoft\EvidenceRiskReview\Log\DatabaseReviewLogStore;
use Throwable;

final class EvidenceLogCommand extends Command
{
    protected $signature = 'evidence:log
        {--limit=25 : Maximum database rows to return.}
        {--pretty : Pretty-print JSON output.}';

    protected $description = 'Show review log entries for the configured null, array, or database log store.';

    public function __construct(
        private readonly ReviewLogStore $store,
        private readonly ConfigRepository $config,
        private readonly DatabaseManager $database,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->line($this->json($this->payload()));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        if ($this->store instanceof ArrayReviewLogStore) {
            return [
                'store' => 'array',
                'entries' => $this->store->entries(),
            ];
        }

        if ($this->store instanceof DatabaseReviewLogStore) {
            return [
                'store' => 'database',
                'entries' => $this->databaseEntries(),
            ];
        }

        return [
            'store' => 'null',
            'entries' => [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function databaseEntries(): array
    {
        $connectionName = $this->config->get('evidence-risk-review.review_log.connection');
        $table = $this->config->get('evidence-risk-review.review_log.table', 'evidence_risk_review_logs');

        $rows = $this->database
            ->connection(is_string($connectionName) ? $connectionName : null)
            ->table(is_string($table) ? $table : 'evidence_risk_review_logs')
            ->orderByDesc('id')
            ->limit($this->limit())
            ->get();

        $entries = [];

        foreach ($rows as $row) {
            if (! is_object($row)) {
                continue;
            }

            $entries[] = [
                'review_id' => $this->property($row, 'review_id'),
                'artifact_id' => $this->property($row, 'artifact_id'),
                'profile_key' => $this->property($row, 'profile_key'),
                'risk_score' => $this->property($row, 'risk_score'),
                'findings' => $this->decode($this->property($row, 'findings')),
                'claim_verdicts' => $this->decode($this->property($row, 'claim_verdicts')),
                'source_tiers' => $this->decode($this->property($row, 'source_tiers')),
                'budget' => $this->decode($this->property($row, 'budget')),
                'artifact' => $this->decode($this->property($row, 'artifact')),
                'options' => $this->decode($this->property($row, 'options')),
                'metadata' => $this->decode($this->property($row, 'metadata')),
                'reviewed_at' => $this->property($row, 'reviewed_at'),
                'created_at' => $this->property($row, 'created_at'),
            ];
        }

        return $entries;
    }

    private function limit(): int
    {
        $limit = $this->option('limit');

        if (is_string($limit) && preg_match('/^\d+$/', $limit) === 1) {
            return max(1, (int) $limit);
        }

        return 25;
    }

    private function property(object $row, string $property): mixed
    {
        return $row->{$property} ?? null;
    }

    private function decode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        try {
            return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $value;
        }
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
