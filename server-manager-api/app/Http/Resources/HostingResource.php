<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsAssetFields;
use App\Models\Hosting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full hosting payload.
 *
 * @mixin Hosting
 */
class HostingResource extends JsonResource
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

            // Vendor: the catalogued provider when linked, else the ad-hoc pair.
            'provider_id' => $hosting->provider_id,
            'provider_name' => $hosting->provider_name,
            'provider_url' => $hosting->provider_url,
            'provider_label' => $hosting->relationLoaded('provider')
                ? $hosting->providerLabel()
                : $hosting->provider_name,
            'provider_link' => $hosting->relationLoaded('provider')
                ? $hosting->providerLink()
                : $hosting->provider_url,

            'server_type' => $this->enumPayload($hosting->server_type),
            'server_location' => $hosting->server_location,
            'ip_address' => $hosting->ip_address,
            'control_panel_url' => $hosting->control_panel_url,

            'username' => $hosting->username,
            'ftp_username' => $hosting->ftp_username,
            // Secrets are never serialised — only whether one is on file.
            'has_password' => $this->hasSecret($hosting, 'password'),
            'has_ftp_password' => $this->hasSecret($hosting, 'ftp_password'),

            'purchase_date' => $this->dateOnly($hosting->purchase_date),
            'expiry_date' => $this->dateOnly($hosting->expiry_date),

            'plan' => $hosting->plan,
            'price' => $hosting->price,
            'currency' => $hosting->currency,

            'project_id' => $hosting->project_id,
            'client_id' => $hosting->client_id,

            'status' => $this->enumPayload($hosting->status),
            'ssl_certificate' => $this->enumPayload($hosting->ssl_certificate),

            'expiry_notification' => (bool) $hosting->expiry_notification,
            'notification_days_before' => (int) $hosting->notification_days_before,
            'expiry_notified_at' => $this->timestamp($hosting->expiry_notified_at),

            'description' => $hosting->description,

            // Computed expiry state, so the UI never re-implements the maths.
            'days_until_expiry' => $hosting->daysUntilExpiry(),
            'is_expired' => $hosting->isExpired(),
            'is_expiring_soon' => $hosting->isExpiringSoon(),

            'domains_count' => $this->whenCounted('domains'),

            'provider' => $this->whenLoaded(
                'provider',
                fn (): ?array => $this->reference($hosting->provider, 'name', ['url']),
            ),
            'project' => $this->whenLoaded(
                'project',
                fn (): ?array => $this->reference($hosting->project),
            ),
            'client' => $this->whenLoaded(
                'client',
                fn (): ?array => $this->reference($hosting->client, 'name', ['company']),
            ),
            'domains' => $this->whenLoaded(
                'domains',
                fn (): AnonymousResourceCollection => DomainSummaryResource::collection($hosting->domains),
            ),

            'created_by' => $this->whenLoaded(
                'creator',
                fn (): ?array => $this->reference($hosting->creator),
            ),
            'updated_by' => $this->whenLoaded(
                'editor',
                fn (): ?array => $this->reference($hosting->editor),
            ),

            'created_at' => $this->timestamp($hosting->created_at),
            'updated_at' => $this->timestamp($hosting->updated_at),
            'deleted_at' => $this->timestamp($hosting->deleted_at),
        ];
    }
}
