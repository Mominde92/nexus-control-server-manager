<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ActivityLogRepositoryInterface;
use App\Contracts\Services\DashboardServiceInterface;
use App\Enums\DomainStatus;
use App\Enums\HostingStatus;
use App\Models\ActivityLog;
use App\Models\Domain;
use App\Models\Hosting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Aggregated dashboard reads.
 *
 * Two deliberate performance decisions:
 *
 *  1. The six cards are resolved by exactly two conditional-aggregate queries
 *     (one per table). No models are hydrated, so the cost is independent of
 *     how many hostings or domains exist.
 *  2. The result is cached for 60 seconds. A dashboard that is a minute stale is
 *     fine; a dashboard that re-aggregates on every poll is not.
 *
 * Rather than depending on the Hosting/Domain repositories, this service reads
 * the models directly: the payload it needs is narrower than the generic CRUD
 * surface, and keeping it self-contained means a change to a module repository
 * cannot quietly change a dashboard number.
 */
class DashboardService implements DashboardServiceInterface
{
    /**
     * Cache key for the statistics payload.
     */
    public const string STATISTICS_CACHE_KEY = 'dashboard:statistics';

    /**
     * Short enough that the numbers feel live, long enough to absorb a room full
     * of open dashboards polling at once.
     */
    public const int STATISTICS_CACHE_TTL = 60;

    /**
     * Column-limited eager loads for the expiring lists. Every relation the
     * ExpiringItemResource touches is listed here, because lazy loading is
     * disabled outside production and would otherwise throw.
     *
     * @var array<int, string>
     */
    private const array EXPIRING_RELATIONS = [
        'provider:id,name,url',
        'client:id,name,company',
        'project:id,name',
    ];

    public function __construct(
        private readonly ActivityLogRepositoryInterface $logs,
    ) {}

    /**
     * @return array{
     *     total_hostings: int,
     *     active_hostings: int,
     *     total_domains: int,
     *     active_domains: int,
     *     expiring_hostings: int,
     *     expiring_domains: int,
     * }
     */
    public function statistics(): array
    {
        $days = $this->windowDays();

        /** @var array{total_hostings: int, active_hostings: int, total_domains: int, active_domains: int, expiring_hostings: int, expiring_domains: int} */
        return Cache::remember(
            self::STATISTICS_CACHE_KEY,
            self::STATISTICS_CACHE_TTL,
            function () use ($days): array {
                $hostings = $this->aggregate(Hosting::query(), HostingStatus::Active->value, $days);
                $domains = $this->aggregate(Domain::query(), DomainStatus::Active->value, $days);

                return [
                    'total_hostings' => $hostings['total'],
                    'active_hostings' => $hostings['active'],
                    'total_domains' => $domains['total'],
                    'active_domains' => $domains['active'],
                    'expiring_hostings' => $hostings['expiring'],
                    'expiring_domains' => $domains['expiring'],
                ];
            },
        );
    }

    /**
     * @return Collection<int, Hosting>
     */
    public function expiringHostings(int $days, int $limit = 10): Collection
    {
        /** @var Collection<int, Hosting> */
        return Hosting::query()
            ->with(self::EXPIRING_RELATIONS)
            ->expiringWithin(max(0, $days))
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * @return Collection<int, Domain>
     */
    public function expiringDomains(int $days, int $limit = 10): Collection
    {
        /** @var Collection<int, Domain> */
        return Domain::query()
            ->with(self::EXPIRING_RELATIONS)
            ->expiringWithin(max(0, $days))
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function recentActivities(int $limit = 15): Collection
    {
        return $this->logs->recent($limit);
    }

    public function windowDays(): int
    {
        return max(1, (int) Config::get('app.expiry.window_days', 30));
    }

    public function flushStatistics(): void
    {
        Cache::forget(self::STATISTICS_CACHE_KEY);
    }

    /**
     * Total / active / expiring-soon for one table in a single round trip.
     *
     * `SUM(CASE WHEN … )` rather than three separate `COUNT(*)` queries: one
     * table scan instead of three. Date comparisons are passed as bound
     * parameters against a DATE column, which keeps the predicate index-usable
     * and portable (no vendor-specific INTERVAL arithmetic).
     *
     * @param  Builder<Hosting>|Builder<Domain>  $query
     * @return array{total: int, active: int, expiring: int}
     */
    private function aggregate(Builder $query, string $activeStatus, int $days): array
    {
        $from = now()->startOfDay()->toDateString();
        $to = now()->startOfDay()->addDays($days)->toDateString();

        /** @var object{total: int|string|null, active: int|string|null, expiring: int|string|null}|null $row */
        $row = $query
            ->toBase()
            ->selectRaw(
                'COUNT(*) AS total,'
                .' SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS active,'
                .' SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date >= ? AND expiry_date <= ? THEN 1 ELSE 0 END) AS expiring',
                [$activeStatus, $from, $to],
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'expiring' => (int) ($row->expiring ?? 0),
        ];
    }
}
