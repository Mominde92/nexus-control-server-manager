<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsAssetFields;
use App\Models\Hosting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim hosting payload for embedding: dashboard expiry lists, related-record
 * panels and pickers. Carries only what a row or a link needs.
 *
 * @mixin Hosting
 */
class HostingSummaryResource extends JsonResource
{
    use FormatsAssetFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($request);

        /** @var Hosting $hosting */
        $hosting = $this->resource;

        return [
            'id' => (int) $hosting->getKey(),
            'title' => $hosting->title,
            'provider_label' => $hosting->relationLoaded('provider')
                ? $hosting->providerLabel()
                : $hosting->provider_name,
            'server_type' => $this->enumPayload($hosting->server_type),
            'status' => $this->enumPayload($hosting->status),
            'expiry_date' => $this->dateOnly($hosting->expiry_date),
            'days_until_expiry' => $hosting->daysUntilExpiry(),
            'is_expired' => $hosting->isExpired(),
            'is_expiring_soon' => $hosting->isExpiringSoon(),
            'client' => $this->whenLoaded(
                'client',
                fn (): ?array => $this->reference($hosting->client, 'name', ['company']),
            ),
        ];
    }
}
