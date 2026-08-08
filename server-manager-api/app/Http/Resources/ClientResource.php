<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsEnums;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'website' => $this->website,
            'address' => $this->address,
            'notes' => $this->notes,

            'projects_count' => $this->whenCounted('projects'),
            'hostings_count' => $this->whenCounted('hostings'),
            'domains_count' => $this->whenCounted('domains'),

            'created_at' => $this->isoDate($this->created_at),
            'updated_at' => $this->isoDate($this->updated_at),
            'deleted_at' => $this->isoDate($this->deleted_at),
        ];
    }
}
