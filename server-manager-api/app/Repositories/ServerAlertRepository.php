<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ServerAlertRepositoryInterface;
use App\Models\ServerAlert;
use App\Support\QueryOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alert persistence.
 *
 * @extends BaseRepository<ServerAlert>
 */
class ServerAlertRepository extends BaseRepository implements ServerAlertRepositoryInterface
{
    /**
     * @return class-string<ServerAlert>
     */
    public function modelClass(): string
    {
        return ServerAlert::class;
    }

    /**
     * @return array<int, string>
     */
    protected function defaultRelations(): array
    {
        return ['server:id,name,hostname,os'];
    }

    /**
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return ['severity', 'type', 'server_id'];
    }

    /**
     * Adds the `status` pseudo-filter, which maps to the presence of
     * `resolved_at` / `acknowledged_at` rather than to a column of its own.
     *
     * @param  Builder<ServerAlert>  $query
     */
    protected function applyFilters(Builder $query, QueryOptions $options): void
    {
        parent::applyFilters($query, $options);

        $status = $options->filter('status');

        if (! is_string($status) || $status === '') {
            return;
        }

        match ($status) {
            'open' => $query->open(),
            'resolved' => $query->resolved(),
            // Acknowledged means "open, but a human has seen it" — a resolved
            // alert is no longer actionable, so it is excluded.
            'acknowledged' => $query->open()->whereNotNull('acknowledged_at'),
            'unacknowledged' => $query->open()->whereNull('acknowledged_at'),
            default => null,
        };
    }

    /**
     * Open counts per severity, in ONE grouped query.
     *
     * @return array<string, int>
     */
    public function openCountsBySeverity(): array
    {
        /** @var Collection<int, object{severity: string, aggregate: int|string}> $rows */
        $rows = DB::table('server_alerts')
            ->selectRaw('severity, count(*) as aggregate')
            ->whereNull('resolved_at')
            ->groupBy('severity')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->severity] = (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * The dedupe lookup: at most one open alert per condition per server.
     *
     * Single indexed hit on `sa_dedupe_idx` (server_id, fingerprint,
     * resolved_at), which is what keeps ingest's alert handling O(1).
     */
    public function findOpenByFingerprint(int $serverId, string $fingerprint): ?ServerAlert
    {
        return ServerAlert::query()
            ->where('server_id', $serverId)
            ->where('fingerprint', $fingerprint)
            ->whereNull('resolved_at')
            ->first();
    }
}
