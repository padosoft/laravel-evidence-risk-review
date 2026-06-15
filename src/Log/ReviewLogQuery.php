<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Log;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use Padosoft\EvidenceRiskReview\Contracts\TenantResolver;
use Padosoft\EvidenceRiskReview\Enums\RiskVerdict;
use Padosoft\EvidenceRiskReview\Tenancy\NullTenantResolver;
use stdClass;

final readonly class ReviewLogQuery
{
    private const MAX_PER_PAGE = 100;

    private const DEFAULT_PER_PAGE = 20;

    private TenantResolver $tenants;

    public function __construct(
        private ReviewLogStore $store,
        private ConfigRepository $config,
        private DatabaseManager $database,
        ?TenantResolver $tenants = null,
    ) {
        $this->tenants = $tenants ?? new NullTenantResolver;
    }

    /**
     * Look up one review by id, scoped to the current tenant when a host tenant
     * resolver is bound (so one tenant can never read another's review).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $reviewId): ?array
    {
        $tenant = $this->tenants->current();

        if ($this->store instanceof ArrayReviewLogStore) {
            foreach ($this->store->entries() as $entry) {
                $result = $entry['result'];

                if (($result['review_id'] ?? null) !== $reviewId) {
                    continue;
                }

                if ($tenant !== null && $this->entryTenant($entry) !== $tenant) {
                    return null;
                }

                return $result;
            }

            return null;
        }

        if ($this->store instanceof DatabaseReviewLogStore) {
            $row = $this->table()
                ->where('review_id', $reviewId)
                ->when($tenant !== null, static fn (Builder $query) => $query->where('tenant_id', $tenant))
                ->first();

            return $row instanceof stdClass ? $this->databaseRow($row) : null;
        }

        return null;
    }

    /**
     * A paginated, tenant-scoped, filterable list of reviews (newest first).
     * When a host tenant resolver is bound, the tenant filter is FORCED to the
     * active tenant — a client-supplied `tenant` filter cannot widen the scope.
     *
     * @param  array<string, mixed>  $filters  page, per_page, tenant, profile, min_verdict
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, per_page: int, total: int}
     */
    public function paginate(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $tenant = $this->tenants->current() ?? $this->stringFilter($filters, 'tenant');
        $profile = $this->stringFilter($filters, 'profile');
        $minVerdict = $this->verdictFilter($filters);

        $rows = $this->store instanceof ArrayReviewLogStore
            ? $this->arrayRows($tenant, $profile, $minVerdict)
            : ($this->store instanceof DatabaseReviewLogStore
                ? null
                : []);

        if ($rows !== null) {
            return $this->paginateInMemory($rows, $page, $perPage);
        }

        return $this->paginateDatabase($tenant, $profile, $minVerdict, $page, $perPage);
    }

    /**
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, per_page: int, total: int}
     */
    private function paginateDatabase(?string $tenant, ?string $profile, ?RiskVerdict $minVerdict, int $page, int $perPage): array
    {
        $allowedVerdicts = $minVerdict !== null ? RiskVerdict::atLeast($minVerdict) : null;

        $base = fn (): Builder => $this->table()
            ->when($tenant !== null, static fn (Builder $q) => $q->where('tenant_id', $tenant))
            ->when($profile !== null, static fn (Builder $q) => $q->where('profile_key', $profile))
            ->when(
                $allowedVerdicts !== null,
                static fn (Builder $q) => $q->whereIn('max_verdict', $allowedVerdicts),
            );

        $total = (int) $base()->count();

        $records = $base()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        $data = [];
        foreach ($records as $row) {
            if ($row instanceof stdClass) {
                $data[] = $this->listRow($row);
            }
        }

        return $this->envelope($data, $total, $page, $perPage);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, per_page: int, total: int}
     */
    private function paginateInMemory(array $rows, int $page, int $perPage): array
    {
        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return $this->envelope(array_values($slice), $total, $page, $perPage);
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, per_page: int, total: int}
     */
    private function envelope(array $data, int $total, int $page, int $perPage): array
    {
        return [
            'data' => $data,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * Build the in-memory review rows from the array store (tests), applying the
     * same tenant/profile/min-verdict filtering + newest-first ordering as the DB.
     *
     * @return list<array<string, mixed>>
     */
    private function arrayRows(?string $tenant, ?string $profile, ?RiskVerdict $minVerdict): array
    {
        if (! $this->store instanceof ArrayReviewLogStore) {
            return [];
        }

        $rows = [];
        foreach ($this->store->entries() as $entry) {
            $result = $entry['result'];
            $entryTenant = $this->entryTenant($entry);
            $maxVerdict = RiskVerdict::highest($this->verdictValues($result));

            if ($tenant !== null && $entryTenant !== $tenant) {
                continue;
            }
            if ($profile !== null && ($result['profile_key'] ?? null) !== $profile) {
                continue;
            }
            if ($minVerdict !== null && $maxVerdict->severity() < $minVerdict->severity()) {
                continue;
            }

            $rows[] = [
                'review_id' => $result['review_id'] ?? null,
                'artifact_id' => $result['artifact_id'] ?? null,
                'profile_key' => $result['profile_key'] ?? null,
                'max_verdict' => $maxVerdict->value,
                'risk_score' => (float) ($result['risk_score'] ?? 0),
                'tenant_id' => $entryTenant,
                'created_at' => $result['reviewed_at'] ?? null,
            ];
        }

        // Newest first: entries are appended in chronological order.
        return array_reverse($rows);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function entryTenant(array $entry): ?string
    {
        $artifact = $entry['artifact'] ?? [];
        $tenant = is_array($artifact) ? ($artifact['tenant_id'] ?? null) : null;

        return is_string($tenant) && $tenant !== '' ? $tenant : null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<string>
     */
    private function verdictValues(array $result): array
    {
        $verdicts = $result['claim_verdicts'] ?? [];

        if (! is_array($verdicts)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $v): string => (string) $v, $verdicts));
    }

    private function table(): Builder
    {
        return $this->database
            ->connection($this->connectionName())
            ->table($this->tableName());
    }

    private function connectionName(): ?string
    {
        $connection = $this->config->get('evidence-risk-review.review_log.connection');

        return is_string($connection) ? $connection : null;
    }

    private function tableName(): string
    {
        $table = $this->config->get('evidence-risk-review.review_log.table', 'evidence_risk_review_logs');

        return is_string($table) ? $table : 'evidence_risk_review_logs';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stringFilter(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function verdictFilter(array $filters): ?RiskVerdict
    {
        $value = $filters['min_verdict'] ?? null;

        return is_string($value) && $value !== '' ? RiskVerdict::tryFrom($value) : null;
    }

    /**
     * The compact row shape consumed by the admin review log list.
     *
     * @return array<string, mixed>
     */
    private function listRow(stdClass $row): array
    {
        return [
            'review_id' => $this->property($row, 'review_id'),
            'artifact_id' => $this->property($row, 'artifact_id'),
            'profile_key' => $this->property($row, 'profile_key'),
            'max_verdict' => $this->property($row, 'max_verdict') ?? RiskVerdict::Keep->value,
            'risk_score' => (float) $this->property($row, 'risk_score'),
            'tenant_id' => $this->property($row, 'tenant_id'),
            'created_at' => $this->property($row, 'created_at') ?? $this->property($row, 'reviewed_at'),
        ];
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
            'tenant_id' => $this->property($row, 'tenant_id'),
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
