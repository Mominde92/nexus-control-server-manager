<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsAssetFields;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim server payload for embedding: the attention list, related-record panels
 * and pickers. Carries only what a row or a link needs.
 *
 * @mixin Server
 */
class ServerSummaryResource extends JsonResource
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

        $severity = $server->getAttribute('open_alert_severity');
        $health = $server->health(is_string($severity) ? $severity : null);

        return [
            'id' => (int) $server->getKey(),
            'name' => $server->name,
            'hostname' => $server->hostname,
            'ip_address' => $server->ip_address,
            'os' => $this->enumPayload($server->os),
            'monitoring_enabled' => (bool) $server->monitoring_enabled,
            'health' => $this->enumPayload($health),
            'needs_attention' => $health->needsAttention(),
            'is_stale' => $server->isStale(),
            'last_heartbeat_at' => $this->timestamp($server->last_heartbeat_at),
            'open_alert_count' => (int) ($server->getAttribute('open_alert_count') ?? 0),
            'client' => $this->whenLoaded(
                'client',
                fn (): ?array => $this->reference($server->client),
            ),
        ];
    }
}
