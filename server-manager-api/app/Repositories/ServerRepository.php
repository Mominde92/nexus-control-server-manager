<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ServerRepositoryInterface;
use App\Enums\ServerHealth;
use App\Models\Server;
use App\Models\ServerAlert;
use App\Support\QueryOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Server persistence.
 *
 * Every read here is written on the assumption that the estate is unbounded:
 * nothing iterates servers in PHP to compute a total, and the list query costs
 * the same per page whether the table holds ten rows or a hundred thousand.
 *
 * @extends BaseRepository<Server>
 */
class ServerRepository extends BaseRepository implements ServerRepositoryInterface
{
    /**
     * @return class-string<Server>
     */
    public function modelClass(): string
    {
        return Server::class;
    }

    /**
     * Column-limited: the list renders only the owner labels.
     *
     * @return array<int, string>
     */
    protected function defaultRelations(): array
    {
        return ['hosting:id,title', 'client:id,name', 'project:id,name'];
    }

    /**
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return ['os', 'monitoring_enabled', 'hosting_id', 'client_id', 'project_id'];
    }

    /**
     * Base query with the derived-health columns attached.
     *
     * `open_alert_severity` and `open_alert_count` arrive as correlated
     * subqueries on the same round trip as the page itself, which is what lets
     * ServerResource call `health()` for every row without touching the alerts
     * table again.
     *
     * @return Builder<Server>
     */
    public function withOpenAlertSeverity(): Builder
    {
        $query = $this->query();

        return $query
            ->addSelect([
                'open_alert_severity' => $this->worstSeveritySubquery($query),
                'open_alert_count' => ServerAlert::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('server_alerts.server_id', 'servers.id')
                    ->whereNull('server_alerts.resolved_at'),
            ]);
    }

    /**
     * Worst open severity for the correlated server, as a single scalar.
     *
     * `MIN` over the severity *rank* (critical = 0) rather than over the string,
     * because alphabetical ordering would rank "critical" below "warning".
     *
     * @param  Builder<Server>  $outer
     * @return \Closure(QueryBuilder): void
     */
    private function worstSeveritySubquery(Builder $outer): \Closure
    {
        unset($outer);

        return static function (QueryBuilder $sub): void {
            $sub->selectRaw(
                "case min(case severity when 'critical' then 0 when 'warning' then 1 else 2 end)"
                ." when 0 then 'critical' when 1 then 'warning' else null end"
            )
                ->from('server_alerts')
                ->whereColumn('server_alerts.server_id', 'servers.id')
                ->whereNull('server_alerts.resolved_at');
        };
    }

    /**
     * Adds the health filter and the alert-severity columns on top of the
     * inherited exact-match filtering.
     *
     * @param  Builder<Server>  $query
     */
    protected function applyFilters(Builder $query, QueryOptions $options): void
    {
        parent::applyFilters($query, $options);

        // Attach the derived columns to every listed row, so the Resource can
        // compute health without a per-row query.
        $query->addSelect([
            'open_alert_severity' => $this->worstSeveritySubquery($query),
            'open_alert_count' => ServerAlert::query()
                ->selectRaw('count(*)')
                ->whereColumn('server_alerts.server_id', 'servers.id')
                ->whereNull('server_alerts.resolved_at'),
        ]);

        $health = $options->filter('health');

        if (is_string($health) && $health !== '') {
            $this->applyHealthFilter($query, $health);
        }
    }

    /**
     * Translate a health value into SQL.
     *
     * Health is derived, not stored, so it cannot be filtered with a simple
     * `where`. Each branch reproduces the precedence in `Server::health()`:
     * disabled > pending > offline > alert severity > healthy.
     *
     * The offline comparison uses the widest plausible tolerance
     * (interval + grace) computed in SQL from the row's own columns, so a host
     * with a bespoke interval is still classified by its own rules.
     *
     * @param  Builder<Server>  $query
     */
    private function applyHealthFilter(Builder $query, string $health): void
    {
        $case = ServerHealth::tryFromValue($health);

        if ($case === null) {
            return;
        }

        match ($case) {
            ServerHealth::Disabled => $query->where('servers.monitoring_enabled', false),

            ServerHealth::Pending => $query
                ->where('servers.monitoring_enabled', true)
                ->whereNull('servers.last_heartbeat_at'),

            ServerHealth::Offline => $query
                ->where('servers.monitoring_enabled', true)
                ->whereNotNull('servers.last_heartbeat_at')
                ->whereRaw($this->staleExpression()),

            ServerHealth::Critical, ServerHealth::Warning => $query
                ->where('servers.monitoring_enabled', true)
                ->whereNotNull('servers.last_heartbeat_at')
                ->whereRaw('not ('.$this->staleExpression().')')
                ->whereExists(fn (QueryBuilder $sub) => $sub
                    ->selectRaw('1')
                    ->from('server_alerts')
                    ->whereColumn('server_alerts.server_id', 'servers.id')
                    ->whereNull('server_alerts.resolved_at')
                    ->where('server_alerts.severity', $case->value)),

            ServerHealth::Healthy => $query
                ->where('servers.monitoring_enabled', true)
                ->whereNotNull('servers.last_heartbeat_at')
                ->whereRaw('not ('.$this->staleExpression().')')
                ->whereNotExists(fn (QueryBuilder $sub) => $sub
                    ->selectRaw('1')
                    ->from('server_alerts')
                    ->whereColumn('server_alerts.server_id', 'servers.id')
                    ->whereNull('server_alerts.resolved_at')
                    ->whereIn('server_alerts.severity', ['critical', 'warning'])),
        };
    }

    /**
     * SQL mirroring `Server::offlineAfterSeconds()`: stale when the seconds
     * since the last heartbeat exceed max(grace, interval + 30).
     *
     * Uses TIMESTAMPDIFF-free arithmetic via UNIX_TIMESTAMP so the comparison
     * stays on one expression, and reads the tolerance from the row's own
     * columns rather than a hardcoded constant.
     */
    private function staleExpression(): string
    {
        // Compare a UTC literal against the column, NOT unix_timestamp()/NOW().
        //
        // Laravel stores timestamps in UTC, but MySQL's unix_timestamp() and
        // NOW() interpret a bare DATETIME in the *session* timezone. On a host
        // running at +04 that reads every UTC heartbeat as four hours in the
        // future, so healthy servers are reported offline — a bug invisible in
        // UTC-configured development that appears only once deployed.
        $now = now()->utc()->format('Y-m-d H:i:s');

        // Tolerance mirrors Server::offlineAfterSeconds(): max(grace, interval+30),
        // read from the row's own columns so each server keeps its own budget.
        $tolerance = 'greatest(coalesce(nullif(servers.heartbeat_grace_seconds, 0), 150), '
            .'coalesce(nullif(servers.heartbeat_interval_seconds, 0), 60) + 30)';

        // Interval arithmetic with a *column* operand is not portable, so the
        // expression is chosen per driver rather than assumed.
        $driver = $this->newModel()->getConnection()->getDriverName();

        return match ($driver) {
            'pgsql' => "servers.last_heartbeat_at + make_interval(secs => {$tolerance}) < timestamp '{$now}'",
            'sqlite' => "datetime(servers.last_heartbeat_at, '+' || ({$tolerance}) || ' seconds') < datetime('{$now}')",
            // MySQL / MariaDB.
            default => "date_add(servers.last_heartbeat_at, interval ({$tolerance}) second) < '{$now}'",
        };
    }

    /**
     * Estate health in ONE aggregate query.
     *
     * The health precedence is expressed as a single derived column, then
     * counted with conditional SUMs. No models are hydrated, so this costs one
     * round trip at any estate size — which is why the dashboard can afford it
     * on every page load.
     *
     * @return array{
     *     total: int, monitored: int, healthy: int, warning: int,
     *     critical: int, offline: int, pending: int, disabled: int
     * }
     */
    public function summaryCounts(): array
    {
        $stale = $this->staleExpression();

        // Worst open severity per server, correlated once and reused by every
        // branch of the CASE below.
        $severity = '(select case min(case sa.severity when \'critical\' then 0'
            .' when \'warning\' then 1 else 2 end) when 0 then \'critical\''
            .' when 1 then \'warning\' else \'none\' end'
            .' from server_alerts sa where sa.server_id = servers.id'
            .' and sa.resolved_at is null)';

        $healthExpression = "case
            when servers.monitoring_enabled = 0 then 'disabled'
            when servers.last_heartbeat_at is null then 'pending'
            when {$stale} then 'offline'
            when coalesce({$severity}, 'none') = 'critical' then 'critical'
            when coalesce({$severity}, 'none') = 'warning' then 'warning'
            else 'healthy'
        end";

        /** @var object{total: int|string, monitored: int|string, healthy: int|string, warning: int|string, critical: int|string, offline: int|string, pending: int|string, disabled: int|string}|null $row */
        $row = DB::table('servers')
            ->whereNull('servers.deleted_at')
            ->selectRaw("
                count(*) as total,
                sum(case when servers.monitoring_enabled = 1 then 1 else 0 end) as monitored,
                sum(case when {$healthExpression} = 'healthy' then 1 else 0 end) as healthy,
                sum(case when {$healthExpression} = 'warning' then 1 else 0 end) as warning,
                sum(case when {$healthExpression} = 'critical' then 1 else 0 end) as critical,
                sum(case when {$healthExpression} = 'offline' then 1 else 0 end) as offline,
                sum(case when {$healthExpression} = 'pending' then 1 else 0 end) as pending,
                sum(case when {$healthExpression} = 'disabled' then 1 else 0 end) as disabled
            ")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'monitored' => (int) ($row->monitored ?? 0),
            'healthy' => (int) ($row->healthy ?? 0),
            'warning' => (int) ($row->warning ?? 0),
            'critical' => (int) ($row->critical ?? 0),
            'offline' => (int) ($row->offline ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'disabled' => (int) ($row->disabled ?? 0),
        ];
    }

    /**
     * Worst-first list of servers needing attention, hard-bounded by `$limit`.
     *
     * Ordering happens in SQL on the same derived expression the summary uses,
     * so this never sorts a hydrated collection of the whole estate.
     *
     * @return Collection<int, Server>
     */
    public function needingAttention(int $limit = 10): Collection
    {
        $stale = $this->staleExpression();

        $severity = '(select case min(case sa.severity when \'critical\' then 0'
            .' when \'warning\' then 1 else 2 end) when 0 then 0 when 1 then 2'
            .' else 9 end from server_alerts sa where sa.server_id = servers.id'
            .' and sa.resolved_at is null)';

        // Ranks match ServerHealth::severity(): critical 0, offline 1, warning 2.
        $rank = "case
            when servers.monitoring_enabled = 0 then 9
            when servers.last_heartbeat_at is null then 8
            when {$stale} then 1
            else coalesce({$severity}, 9)
        end";

        return $this->query()
            ->with($this->defaultRelations())
            ->addSelect([
                'open_alert_severity' => $this->worstSeveritySubquery($this->query()),
                'open_alert_count' => ServerAlert::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('server_alerts.server_id', 'servers.id')
                    ->whereNull('server_alerts.resolved_at'),
            ])
            ->selectRaw("servers.*, ({$rank}) as attention_rank")
            ->whereRaw("({$rank}) <= 2")
            ->orderByRaw('attention_rank asc')
            ->orderBy('servers.last_heartbeat_at')
            ->limit(max(1, $limit))
            ->get();
    }

    public function findWithTrashed(int $id): ?Server
    {
        return $this->query()
            ->withTrashed()
            ->with($this->defaultRelations())
            ->find($id);
    }
}
