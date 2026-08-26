<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Server persistence.
 *
 * @extends RepositoryInterface<Server>
 */
interface ServerRepositoryInterface extends RepositoryInterface
{
    /**
     * Estate-wide health counts.
     *
     * Computed by ONE aggregate query over `servers` (plus a correlated
     * open-alert subquery), never by loading models — so the cost is a single
     * round trip whether there are 5 servers or 50,000.
     *
     * @return array{
     *     total: int, monitored: int, healthy: int, warning: int,
     *     critical: int, offline: int, pending: int, disabled: int
     * }
     */
    public function summaryCounts(): array;

    /**
     * A query with the worst open-alert severity attached per row as
     * `open_alert_severity`, plus `open_alert_count`.
     *
     * Lets the paginated list derive health for every row with no per-row query:
     * `Server::health($row->open_alert_severity)`.
     *
     * @return Builder<Server>
     */
    public function withOpenAlertSeverity(): Builder;

    /**
     * Servers whose health needs an operator's attention (critical, offline or
     * warning), worst first. Bounded by `$limit` — never the whole estate.
     *
     * @return Collection<int, Server>
     */
    public function needingAttention(int $limit = 10): Collection;

    public function findWithTrashed(int $id): ?Server;
}
