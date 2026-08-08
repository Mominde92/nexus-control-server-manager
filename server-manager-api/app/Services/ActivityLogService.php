<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ActivityLogRepositoryInterface;
use App\Contracts\Services\ActivityLogServiceInterface;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Read and retention operations over the audit trail.
 *
 * Writes are refused rather than silently ignored: if application code ever
 * tries to author an audit row by hand, that is a bug worth surfacing loudly.
 * The single legitimate writer is App\Services\ActivityLogger.
 *
 * @extends BaseCrudService<ActivityLog>
 */
class ActivityLogService extends BaseCrudService implements ActivityLogServiceInterface
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $logs,
    ) {
        parent::__construct($logs);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException
     */
    public function create(array $data): Model
    {
        unset($data);

        throw new RuntimeException(
            'Activity logs are append-only and may only be written by ActivityLoggerInterface.',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException
     */
    public function update(Model $model, array $data): Model
    {
        unset($model, $data);

        throw new RuntimeException('Activity logs are immutable and cannot be updated.');
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function recent(int $limit = 15): Collection
    {
        return $this->logs->recent($limit);
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function forSubject(string $subjectType, int $subjectId): Collection
    {
        return $this->logs->forSubject($subjectType, $subjectId);
    }

    public function prune(int $days): int
    {
        return $this->logs->pruneOlderThan($days);
    }
}
