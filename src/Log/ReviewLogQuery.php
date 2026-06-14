<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Log;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use stdClass;

final readonly class ReviewLogQuery
{
    public function __construct(
        private ReviewLogStore $store,
        private ConfigRepository $config,
        private DatabaseManager $database,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $reviewId): ?array
    {
        if ($this->store instanceof ArrayReviewLogStore) {
            foreach ($this->store->entries() as $entry) {
                $result = $entry['result'];

                if (($result['review_id'] ?? null) === $reviewId) {
                    return $result;
                }
            }

            return null;
        }

        if ($this->store instanceof DatabaseReviewLogStore) {
            $row = $this->database
                ->connection($this->connectionName())
                ->table($this->table())
                ->where('review_id', $reviewId)
                ->first();

            return $row instanceof stdClass ? $this->databaseRow($row) : null;
        }

        return null;
    }

    private function connectionName(): ?string
    {
        $connection = $this->config->get('evidence-risk-review.review_log.connection');

        return is_string($connection) ? $connection : null;
    }

    private function table(): string
    {
        $table = $this->config->get('evidence-risk-review.review_log.table', 'evidence_risk_review_logs');

        return is_string($table) ? $table : 'evidence_risk_review_logs';
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseRow(stdClass $row): array
    {
        return [
            'review_id' => $this->property($row, 'review_id'),
            'artifact_id' => $this->property($row, 'artifact_id'),
            'profile_key' => $this->property($row, 'profile_key'),
            'findings' => $this->decode($this->property($row, 'findings')),
            'claim_verdicts' => $this->decode($this->property($row, 'claim_verdicts')),
            'source_tiers' => $this->decode($this->property($row, 'source_tiers')),
            'risk_score' => (float) $this->property($row, 'risk_score'),
            'budget' => $this->decode($this->property($row, 'budget')),
            'reviewed_at' => $this->property($row, 'reviewed_at'),
            'metadata' => $this->decode($this->property($row, 'metadata')),
        ];
    }

    private function property(stdClass $row, string $property): mixed
    {
        return $row->{$property} ?? null;
    }

    private function decode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
