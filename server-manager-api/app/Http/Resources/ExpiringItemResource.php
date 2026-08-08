<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Domain;
use App\Models\Hosting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A hosting or a domain rendered as a row in the dashboard's "expiring soon"
 * lists.
 *
 * Both modules answer the same four questions on that screen — what is it, who
 * is it for, when does it lapse, and where do I go to renew it — so they share
 * one flat shape and the UI renders a single component for both. This resource
 * is deliberately independent of the full HostingResource / DomainResource: the
 * dashboard must not pay for (or be coupled to) the detail payload.
 *
 * Requires the `provider`, `client` and `project` eager loads.
 *
 * @mixin Hosting|Domain
 */
class ExpiringItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($request);

        /** @var Hosting|Domain $item */
        $item = $this->resource;

        $isHosting = $item instanceof Hosting;
        $status = $item->status;

        return [
            'id' => $item->id,
            'type' => $isHosting ? 'hosting' : 'domain',
            'name' => $isHosting ? $item->title : $item->name,

            'provider' => $item->providerLabel(),
            'client' => $this->relationName($item, 'client'),
            'project' => $this->relationName($item, 'project'),

            'expiry_date' => $item->expiry_date?->toDateString(),
            'days_until_expiry' => $item->daysUntilExpiry(),

            'status' => $status === null ? null : [
                'value' => $status->value,
                'label' => $status->label(),
                'variant' => $status->variant(),
            ],

            // Where an operator goes to act on the record: the control panel for
            // a hosting, the registrar for a domain, falling back to the
            // vendor's site.
            'url' => $isHosting
                ? ($item->control_panel_url ?? $item->providerLink())
                : ($item->registrar_url ?? $item->providerLink()),
        ];
    }

    /**
     * Read a `name` off an eager-loaded belongsTo without risking a lazy load
     * when the relation was not requested.
     */
    private function relationName(Model $item, string $relation): ?string
    {
        if (! $item->relationLoaded($relation)) {
            return null;
        }

        $related = $item->getRelation($relation);

        if (! $related instanceof Model) {
            return null;
        }

        $name = $related->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : null;
    }
}
