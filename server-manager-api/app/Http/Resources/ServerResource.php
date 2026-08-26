<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsAssetFields;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full server payload.
 *
 * Two things this resource is careful about:
 *
 *   1. Health is derived from the `open_alert_severity` column the repository
 *      attaches as a subquery, so serialising a page of servers costs no extra
 *      queries. Passing that value into `health()` is what avoids the N+1 the
 *      naive implementation would have.
 *   2. No token, hash or hint is ever exposed — only whether a usable credential
 *      exists. An agent secret leaves the system exactly once, at issue time,
 *      from ServerAgentCredentialResource.
 *
 * @mixin Server
 */
class ServerResource extends JsonResource
{
    use FormatsAssetFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($request);

        /** @var Server $server */
        $server = $this->resource;

        // Present when the row came from a repository query that attached it;
        // null otherwise, in which case health() falls back to the loaded
        // relation.
        $severity = $server->getAttribute('open_alert_severity');
        $health = $server->health(is_string($severity) ? $severity : null);

        return [
            'id' => (int) $server->getKey(),
            'name' => $server->name,
            'hostname' => $server->hostname,
            'ip_address' => $server->ip_address,

            'os' => $this->enumPayload($server->os),
            'os_version' => $server->os_version,

            'hosting_id' => $server->hosting_id,
            'client_id' => $server->client_id,
            'project_id' => $server->project_id,

            'monitoring_enabled' => (bool) $server->monitoring_enabled,

            // Derived health, shipped as {value,label,variant} so the UI renders
            // the badge without re-implementing the rules.
            'health' => $this->enumPayload($health),
            'needs_attention' => $health->needsAttention(),

            'is_stale' => $server->isStale(),
            'last_heartbeat_at' => $this->timestamp($server->last_heartbeat_at),
            'seconds_since_heartbeat' => $this->secondsSinceHeartbeat($server),
            'offline_after_seconds' => $server->offlineAfterSeconds(),

            'agent_version' => $server->agent_version,
            'uptime_seconds' => $server->uptime_seconds,

            'heartbeat_interval_seconds' => $server->heartbeat_interval_seconds,
            'heartbeat_grace_seconds' => $server->heartbeat_grace_seconds,

            'cpu_warning_threshold' => $server->cpu_warning_threshold,
            'cpu_critical_threshold' => $server->cpu_critical_threshold,
            'memory_warning_threshold' => $server->memory_warning_threshold,
            'memory_critical_threshold' => $server->memory_critical_threshold,
            'disk_warning_threshold' => $server->disk_warning_threshold,
            'disk_critical_threshold' => $server->disk_critical_threshold,

            'open_alert_count' => (int) ($server->getAttribute('open_alert_count') ?? 0),

            'notes' => $server->notes,

            // Whether an agent can currently authenticate. Never the credential
            // itself.
            'agent' => [
                'has_active_credential' => $this->hasActiveCredential($server),
            ],

            'latest_metrics' => $this->when(
                $server->getAttribute('latest_metrics') !== null,
                fn (): mixed => $server->getAttribute('latest_metrics'),
            ),

            'hosting' => $this->whenLoaded(
                'hosting',
                fn (): ?array => $this->reference($server->hosting, 'title'),
            ),
            'client' => $this->whenLoaded(
                'client',
                fn (): ?array => $this->reference($server->client),
            ),
            'project' => $this->whenLoaded(
                'project',
                fn (): ?array => $this->reference($server->project),
            ),
            'open_alerts' => $this->whenLoaded(
                'openAlerts',
                fn (): mixed => ServerAlertResource::collection($server->openAlerts),
            ),

            'created_by' => $this->whenLoaded(
                'creator',
                fn (): ?array => $this->reference($server->creator),
            ),
            'updated_by' => $this->whenLoaded(
                'editor',
                fn (): ?array => $this->reference($server->editor),
            ),

            'created_at' => $this->timestamp($server->created_at),
            'updated_at' => $this->timestamp($server->updated_at),
            'deleted_at' => $this->timestamp($server->deleted_at),
        ];
    }

    private function secondsSinceHeartbeat(Server $server): ?int
    {
        $last = $server->last_heartbeat_at;

        return $last === null ? null : (int) $last->diffInSeconds(now());
    }

    /**
     * Resolved from whichever shape the caller eager loaded, and false when
     * neither is available — this must never trigger a lazy query, since
     * preventLazyLoading() is on outside production.
     */
    private function hasActiveCredential(Server $server): bool
    {
        $flag = $server->getAttribute('has_active_credential');

        if ($flag !== null) {
            return (bool) $flag;
        }

        if ($server->relationLoaded('activeCredential')) {
            return $server->activeCredential?->isUsable() ?? false;
        }

        if ($server->relationLoaded('credentials')) {
            return $server->credentials->contains(
                static fn ($credential): bool => $credential->isUsable(),
            );
        }

        return false;
    }
}
