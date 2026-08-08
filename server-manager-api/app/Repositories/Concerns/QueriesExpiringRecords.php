<?php

declare(strict_types=1);

namespace App\Repositories\Concerns;

use App\Support\QueryOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Expiry-window filtering and counting shared by the Hosting and Domain
 * repositories.
 *
 * Both modules describe a purchased asset with an `expiry_date`, a `price` and
 * a `status`, so the dashboard reads and the range filters are identical — only
 * the model differs. Requires the host model to use the HasExpiry concern.
 *
 * @phpstan-require-extends \App\Repositories\BaseRepository
 */
trait QueriesExpiringRecords
{
    /**
     * Filter keys handled here rather than by exact column matching.
     *
     * @var array<int, string>
     */
    private const array RANGE_FILTERS = [
        'expiring_within',
        'expired',
        'expiry_from',
        'expiry_to',
        'price_min',
        'price_max',
    ];

    /**
     * Filters whose value arrives as a query-string boolean ("true", "0", …)
     * and must be coerced before it reaches a SQL comparison against a
     * tinyint column.
     *
     * @return array<int, string>
     */
    protected function booleanFilters(): array
    {
        return [];
    }

    /**
     * Apply the expiry / price range filters.
     *
     * @param  Builder<covariant Model>  $query
     */
    protected function applyRangeFilters(Builder $query, QueryOptions $options): void
    {
        $expiryColumn = $query->qualifyColumn('expiry_date');
        $priceColumn = $query->qualifyColumn('price');

        if ($options->hasFilter('expiring_within')) {
            $days = (int) $options->filter('expiring_within');

            if ($days >= 0) {
                /** @phpstan-ignore-next-line scope provided by HasExpiry */
                $query->expiringWithin($days);
            }
        }

        if ($options->hasFilter('expired') && self::toBool($options->filter('expired'))) {
            /** @phpstan-ignore-next-line scope provided by HasExpiry */
            $query->expired();
        }

        if ($options->hasFilter('expiry_from')) {
            $query->whereDate($expiryColumn, '>=', (string) $options->filter('expiry_from'));
        }

        if ($options->hasFilter('expiry_to')) {
            $query->whereDate($expiryColumn, '<=', (string) $options->filter('expiry_to'));
        }

        if ($options->hasFilter('price_min')) {
            $query->where($priceColumn, '>=', (float) $options->filter('price_min'));
        }

        if ($options->hasFilter('price_max')) {
            $query->where($priceColumn, '<=', (float) $options->filter('price_max'));
        }
    }

    /**
     * A copy of the options with boolean-ish filter values coerced to real
     * booleans, so `?auto_renewal=false` does not match every row.
     *
     * QueryOptions is immutable, hence the rebuild rather than a mutation.
     */
    protected function normaliseFilters(QueryOptions $options): QueryOptions
    {
        $booleanKeys = $this->booleanFilters();

        if ($booleanKeys === []) {
            return $options;
        }

        $filters = $options->filters;

        foreach ($booleanKeys as $key) {
            if (array_key_exists($key, $filters)) {
                $filters[$key] = self::toBool($filters[$key]);
            }
        }

        return new QueryOptions(
            page: $options->page,
            perPage: $options->perPage,
            search: $options->search,
            sort: $options->sort,
            direction: $options->direction,
            filters: $filters,
            with: $options->with,
            withTrashed: $options->withTrashed,
        );
    }

    /**
     * Filter keys this repository understands beyond its exact-match columns.
     *
     * @return array<int, string>
     */
    public function rangeFilterKeys(): array
    {
        return self::RANGE_FILTERS;
    }

    // -----------------------------------------------------------------------
    // Reads
    // -----------------------------------------------------------------------

    /**
     * Fetch including soft-deleted rows — the only way to reach a record that
     * needs restoring, since route-model binding hides trashed models.
     *
     * @return Model
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findWithTrashed(int $id): Model
    {
        $query = $this->query()->with($this->defaultRelations());

        if ($this->usesSoftDeletes()) {
            /** @phpstan-ignore-next-line withTrashed exists on soft-deleting models */
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }

    // -----------------------------------------------------------------------
    // Dashboard reads
    // -----------------------------------------------------------------------

    /**
     * @return Collection<int, Model>
     */
    public function expiringWithin(int $days, int $limit = 0): Collection
    {
        $query = $this->query()
            ->with($this->defaultRelations())
            /** @phpstan-ignore-next-line scope provided by HasExpiry */
            ->expiringWithin($days)
            ->orderBy('expiry_date');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * One grouped query, so the dashboard never fans out into a count per
     * status.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        /** @var array<int, object{status: string|null, aggregate: int|string}> $rows */
        $rows = $this->query()
            ->toBase()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->get()
            ->all();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->status] = (int) $row->aggregate;
        }

        return $counts;
    }

    public function totalCount(): int
    {
        return $this->query()->count();
    }

    public function activeCount(): int
    {
        /** @phpstan-ignore-next-line scope declared on the model */
        return $this->query()->active()->count();
    }

    public function expiringCount(int $days): int
    {
        /** @phpstan-ignore-next-line scope provided by HasExpiry */
        return $this->query()->expiringWithin($days)->count();
    }

    public function expiredCount(): int
    {
        /** @phpstan-ignore-next-line scope provided by HasExpiry */
        return $this->query()->expired()->count();
    }

    /**
     * Reminder candidates. The SQL horizon is coarse (see HasExpiry); each
     * record's own `notification_days_before` window is applied in PHP.
     *
     * @return Collection<int, Model>
     */
    public function dueForNotification(int $horizon = 365): Collection
    {
        return $this->query()
            ->with($this->defaultRelations())
            /** @phpstan-ignore-next-line scope provided by HasExpiry */
            ->dueForExpiryNotification($horizon)
            ->orderBy('expiry_date')
            ->get()
            ->filter(static fn (Model $record): bool => $record->isExpiringSoon())
            ->values();
    }

    /**
     * Query-string booleans arrive as strings ("true"/"false"/"1"/"0").
     */
    private static function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }
}
