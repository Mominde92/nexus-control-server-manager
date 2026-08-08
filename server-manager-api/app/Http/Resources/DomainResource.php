<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsAssetFields;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full domain payload.
 *
 * @mixin Domain
 */
class DomainResource extends JsonResource
{
    use FormatsAssetFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($request);

        /** @var Domain $domain */
        $domain = $this->resource;

        return [
            'id' => (int) $domain->getKey(),
            'name' => $domain->name,

            'provider_id' => $domain->provider_id,
            'provider_name' => $domain->provider_name,
            'provider_url' => $domain->provider_url,
            'provider_label' => $domain->relationLoaded('provider')
                ? $domain->providerLabel()
                : $domain->provider_name,
            'provider_link' => $domain->relationLoaded('provider')
                ? $domain->providerLink()
                : $domain->provider_url,

            'domain_type' => $this->enumPayload($domain->domain_type),
            'hosting_id' => $domain->hosting_id,

            'username' => $domain->username,
            'has_password' => $this->hasSecret($domain, 'password'),

            'purchase_date' => $this->dateOnly($domain->purchase_date),
            'expiry_date' => $this->dateOnly($domain->expiry_date),

            'price' => $domain->price,
            'currency' => $domain->currency,
            'plan' => $domain->plan,

            'registrar_url' => $domain->registrar_url,
            'registrar_username' => $domain->registrar_username,
            'has_registrar_password' => $this->hasSecret($domain, 'registrar_password'),
            'registrar_status' => $this->enumPayload($domain->registrar_status),

            'project_id' => $domain->project_id,
            'client_id' => $domain->client_id,

            'status' => $this->enumPayload($domain->status),

            'auto_renewal' => (bool) $domain->auto_renewal,
            'whois_protection' => (bool) $domain->whois_protection,

            'expiry_notification' => (bool) $domain->expiry_notification,
            'notification_days_before' => (int) $domain->notification_days_before,
            'expiry_notified_at' => $this->timestamp($domain->expiry_notified_at),

            'description' => $domain->description,

            'days_until_expiry' => $domain->daysUntilExpiry(),
            'is_expired' => $domain->isExpired(),
            'is_expiring_soon' => $domain->isExpiringSoon(),

            'provider' => $this->whenLoaded(
                'provider',
                fn (): ?array => $this->reference($domain->provider, 'name', ['url']),
            ),
            'project' => $this->whenLoaded(
                'project',
                fn (): ?array => $this->reference($domain->project),
            ),
            'client' => $this->whenLoaded(
                'client',
                fn (): ?array => $this->reference($domain->client, 'name', ['company']),
            ),
            'hosting' => $this->whenLoaded(
                'hosting',
                fn (): ?array => $this->reference($domain->hosting, 'title'),
            ),

            'created_by' => $this->whenLoaded(
                'creator',
                fn (): ?array => $this->reference($domain->creator),
            ),
            'updated_by' => $this->whenLoaded(
                'editor',
                fn (): ?array => $this->reference($domain->editor),
            ),

            'created_at' => $this->timestamp($domain->created_at),
            'updated_at' => $this->timestamp($domain->updated_at),
            'deleted_at' => $this->timestamp($domain->deleted_at),
        ];
    }
}
