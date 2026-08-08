<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsEnums;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
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
            'description' => $this->description,
            'status' => $this->enumPayload($this->status),

            'client_id' => $this->client_id,

            // `whenLoaded` keeps the payload honest: an omitted relation is
            // absent from the JSON rather than silently null.
            'client' => $this->whenLoaded('client', fn (): ?array => $this->client === null ? null : [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'company' => $this->client->company,
            ]),

            'start_date' => $this->plainDate($this->start_date),
            'end_date' => $this->plainDate($this->end_date),

            'hostings_count' => $this->whenCounted('hostings'),
            'domains_count' => $this->whenCounted('domains'),

            'created_at' => $this->isoDate($this->created_at),
            'updated_at' => $this->isoDate($this->updated_at),
            'deleted_at' => $this->isoDate($this->deleted_at),
        ];
    }
}
