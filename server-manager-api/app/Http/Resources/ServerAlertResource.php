<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsAssetFields;
use App\Models\ServerAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A stateful alert incident.
 *
 * `occurrences` is exposed because it is the operationally interesting number:
 * one alert observed 400 times is a sustained problem, whereas one observed twice
 * is probably a blip. That distinction only exists because ingest dedupes on the
 * fingerprint instead of inserting a row per breaching sample.
 *
 * @mixin ServerAlert
 */
class ServerAlertResource extends JsonResource
{
    use FormatsAssetFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($request);

        /** @var ServerAlert $alert */
        $alert = $this->resource;

        return [
            'id' => (int) $alert->getKey(),
            'server_id' => (int) $alert->server_id,

            'severity' => $this->enumPayload($alert->severity),
            'type' => $alert->type,
            'fingerprint' => $alert->fingerprint,

            'title' => $alert->title,
            'message' => $alert->message,

            'metric_type' => $this->enumPayload($alert->metric_type),
            'observed_value' => $alert->observed_value,
            'threshold_value' => $alert->threshold_value,

            'started_at' => $this->timestamp($alert->started_at),
            'last_observed_at' => $this->timestamp($alert->last_observed_at),
            'resolved_at' => $this->timestamp($alert->resolved_at),
            'occurrences' => (int) $alert->occurrences,

            'acknowledged_at' => $this->timestamp($alert->acknowledged_at),
            'acknowledged_by' => $alert->acknowledged_by,

            'is_open' => $alert->isOpen(),
            'is_acknowledged' => $alert->isAcknowledged(),

            'server' => $this->whenLoaded(
                'server',
                fn (): ?array => $this->reference($alert->server, 'name', ['hostname']),
            ),
            'acknowledger' => $this->whenLoaded(
                'acknowledger',
                fn (): ?array => $this->reference($alert->acknowledger),
            ),

            'created_at' => $this->timestamp($alert->created_at),
        ];
    }
}
