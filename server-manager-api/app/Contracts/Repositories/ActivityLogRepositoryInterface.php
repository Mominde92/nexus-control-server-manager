<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\ActivityLog;
use App\Support\QueryOptions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Audit-trail persistence contract.
 *
 * Deliberately read-oriented: the only writer is App\Services\ActivityLogger.
 * The inherited create/update methods exist because the audit repository still
 * needs to satisfy the generic contract, but the service layer refuses them.
 *
 * @extends RepositoryInterface<ActivityLog>
 */
interface ActivityLogRepositoryInterface extends RepositoryInterface
{
    /**
     * Newest entries first — drives the dashboard timeline.
     *
     * @return Collection<int, ActivityLog>
     */
    public function recent(int $limit = 15): Collection;

    /**
     * Full history for one record, newest first.
     *
     * @param  string  $subjectType  Morph class / FQCN of the subject.
     * @return Collection<int, ActivityLog>
     */
    public function forSubject(string $subjectType, int $subjectId): Collection;

    /**
     * The matching rows as a lazy stream, for CSV export.
     *
     * A full audit trail does not fit comfortably in memory, so exports iterate
     * a chunked cursor instead of materialising a Collection.
     *
     * @return LazyCollection<int, ActivityLog>
     */
    public function stream(QueryOptions $options, int $chunk = 500): LazyCollection;

    /**
     * Delete entries older than $days, in chunks, and report how many rows went.
     */
    public function pruneOlderThan(int $days, int $chunk = 1000): int;
}
