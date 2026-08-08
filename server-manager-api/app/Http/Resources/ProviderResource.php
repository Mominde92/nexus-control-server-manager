<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsEnums;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Provider
 */
class ProviderResource extends JsonResource
{
    use FormatsEnums;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'type' => $this->enumPayload($this->type),
            'status' => $this->enumPayload($this->status),
            'support_email' => $this->support_email,
            'support_phone' => $this->support_phone,
            'notes' => $this->notes,

            // Present only when the query asked for them, so a detail read that
            // skips the counters does not emit misleading zeros.
            'hostings_count' => $this->whenCounted('hostings'),
            'domains_count' => $this->whenCounted('domains'),

            'created_at' => $this->isoDate($this->created_at),
            'updated_at' => $this->isoDate($this->updated_at),
            'deleted_at' => $this->isoDate($this->deleted_at),
        ];
    }
}
