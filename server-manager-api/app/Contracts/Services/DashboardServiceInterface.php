<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\ActivityLog;
use App\Models\Domain;
use App\Models\Hosting;
use Illuminate\Database\Eloquent\Collection;

/**
 * Aggregated reads backing the dashboard.
 *
 * Everything here is a summary: counts come from grouped SQL aggregates and the
 * "expiring soon" lists are hard-limited, so the dashboard cost does not grow
 * with the size of the estate.
 */
interface DashboardServiceInterface
{
    /**
     * The six summary cards.
     *
     * @return array{
     *     total_hostings: int,
     *     active_hostings: int,
     *     total_domains: int,
     *     active_domains: int,
     *     expiring_hostings: int,
     *     expiring_domains: int,
     * }
     */
    public function statistics(): array;

    /**
     * @return Collection<int, Hosting>
     */
    public function expiringHostings(int $days, int $limit = 10): Collection;

    /**
     * @return Collection<int, Domain>
     */
    public function expiringDomains(int $days, int $limit = 10): Collection;

    /**
     * @return Collection<int, ActivityLog>
     */
    public function recentActivities(int $limit = 15): Collection;

    /**
     * The configured "expiring soon" look-ahead in days.
     */
    public function windowDays(): int;

    /**
     * Drop the cached statistics payload — called after a mutation that would
     * change a card, and by tests.
     */
    public function flushStatistics(): void;
}
