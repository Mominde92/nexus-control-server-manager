<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsAssetFields;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim domain payload for embedding in dashboard and related-record payloads.
 *
 * @mixin Domain
 */
class DomainSummaryResource extends JsonResource
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
            'provider_label' => $domain->relationLoaded('provider')
                ? $domain->providerLabel()
                : $domain->provider_name,
            'domain_type' => $this->enumPayload($domain->domain_type),
            'status' => $this->enumPayload($domain->status),
            'registrar_status' => $this->enumPayload($domain->registrar_status),
            'expiry_date' => $this->dateOnly($domain->expiry_date),
            'days_until_expiry' => $domain->daysUntilExpiry(),
            'is_expired' => $domain->isExpired(),
            'is_expiring_soon' => $domain->isExpiringSoon(),
            'auto_renewal' => (bool) $domain->auto_renewal,
            'client' => $this->whenLoaded(
                'client',
                fn (): ?array => $this->reference($domain->client, 'name', ['company']),
            ),
        ];
    }
}
