<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\ActivityLog;
use App\Support\QueryOptions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Read/retention surface over the audit trail.
 *
 * The trail is append-only: `create()` and `update()` inherited from
 * CrudServiceInterface are refused at runtime. Entries are written exclusively
 * by App\Contracts\ActivityLoggerInterface.
 *
 * @extends CrudServiceInterface<ActivityLog>
 */
interface ActivityLogServiceInterface extends CrudServiceInterface
{
    /**
     * @return Collection<int, ActivityLog>
     */
    public function recent(int $limit = 15): Collection;

    /**
     * @return Collection<int, ActivityLog>
     */
    public function forSubject(string $subjectType, int $subjectId): Collection;

    /**
     * Apply the retention policy; returns the number of rows removed.
     */
    public function prune(int $days): int;
}
