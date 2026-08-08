<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ActivityLogRepositoryInterface;
use App\Models\ActivityLog;
use App\Support\QueryOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Audit-trail persistence.
 *
 * @extends BaseRepository<ActivityLog>
 */
class ActivityLogRepository extends BaseRepository implements ActivityLogRepositoryInterface
{
    /**
     * @return class-string<ActivityLog>
     */
    public function modelClass(): string
    {
        return ActivityLog::class;
    }

    /**
     * Only the actor's display name is rendered, so the eager load is column
     * limited rather than hydrating whole user rows.
     *
     * @return array<int, string>
     */
    protected function defaultRelations(): array
    {
        return ['user:id,name'];
    }

    /**
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return ['action', 'user_id', 'subject_type'];
    }

    /**
     * Exact-match columns plus the date range and subject narrowing the audit
     * screen needs.
     *
     * @param  Builder<ActivityLog>  $query
     */
    protected function applyFilters(Builder $query, QueryOptions $options): void
    {
        parent::applyFilters($query, $options);

        $createdAt = $query->qualifyColumn('created_at');

        if ($options->hasFilter('date_from')) {
            $query->whereDate($createdAt, '>=', (string) $options->filter('date_from'));
        }

        if ($options->hasFilter('date_to')) {
            $query->whereDate($createdAt, '<=', (string) $options->filter('date_to'));
        }

        if ($options->hasFilter('subject_id')) {
            $query->where($query->qualifyColumn('subject_id'), (int) $options->filter('subject_id'));
        }
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function recent(int $limit = 15): Collection
    {
        return $this->query()
            ->with($this->defaultRelations())
            ->latest('created_at')
            ->latest('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function forSubject(string $subjectType, int $subjectId): Collection
    {
        return $this->query()
            ->with($this->defaultRelations())
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->latest('created_at')
            ->latest('id')
            ->get();
    }

    /**
     * @return LazyCollection<int, ActivityLog>
     */
    public function stream(QueryOptions $options, int $chunk = 500): LazyCollection
    {
        return $this->buildQuery($options)->lazy(max(1, $chunk));
    }

    /**
     * Chunked delete so a large retention sweep never builds a single enormous
     * transaction (or holds a table-wide lock for the duration).
     */
    public function pruneOlderThan(int $days, int $chunk = 1000): int
    {
        $cutoff = now()->subDays(max(1, $days));
        $deleted = 0;

        do {
            $affected = $this->query()
                ->where('created_at', '<', $cutoff)
                ->limit(max(1, $chunk))
                ->delete();

            $deleted += $affected;
        } while ($affected > 0);

        return $deleted;
    }
}
