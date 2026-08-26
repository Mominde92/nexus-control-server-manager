<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\Services\DashboardServiceInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidates the cached dashboard statistics whenever a Hosting or Domain
 * changes.
 *
 * The counters are cached for 60s (see DashboardService::STATISTICS_CACHE_TTL),
 * which is fine for read load but wrong immediately after a write: an operator
 * who adds a hosting must see the totals move, not a stale snapshot for up to a
 * minute.
 *
 * Wiring this as an observer — rather than calling flushStatistics() from each
 * service — means the cache stays correct no matter how the row changed:
 * service, factory, seeder, tinker or console command.
 */
final class FlushesDashboardCache
{
    public function created(Model $model): void
    {
        $this->flush($model);
    }

    public function updated(Model $model): void
    {
        $this->flush($model);
    }

    public function deleted(Model $model): void
    {
        $this->flush($model);
    }

    public function restored(Model $model): void
    {
        $this->flush($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->flush($model);
    }

    private function flush(Model $model): void
    {
        unset($model);

        app(DashboardServiceInterface::class)->flushStatistics();
    }
}
