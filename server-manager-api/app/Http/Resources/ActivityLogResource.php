<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One audit-trail entry, shaped for the activity timeline.
 *
 * Timestamps are emitted three ways on purpose: ISO-8601 for machines, a
 * pre-formatted `date`/`time` pair because the UI renders `05-08-2026 04:00 PM`
 * literally, and a relative string for the "2 hours ago" label. Formatting here
 * rather than in React keeps one definition of the app's date format.
 *
 * Requires the `user:id,name` eager load.
 *
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($request);

        /** @var ActivityLog $log */
        $log = $this->resource;

        $action = $log->action instanceof ActivityAction ? $log->action : null;
        $createdAt = $log->created_at;

        return [
            'id' => $log->id,

            // Null once the actor is hard-deleted; `user_name` is the
            // denormalised fallback captured at write time.
            'user' => $log->relationLoaded('user') && $log->user !== null
                ? ['id' => $log->user->id, 'name' => $log->user->name]
                : null,
            'user_name' => $log->user_name,

            'action' => $action === null ? null : [
                'value' => $action->value,
                'label' => $action->label(),
                'variant' => $action->variant(),
            ],

            'subject' => $log->subject_type === null ? null : [
                'type' => class_basename($log->subject_type),
                'id' => $log->subject_id === null ? null : (int) $log->subject_id,
                'label' => $log->subject_label,
            ],

            'description' => $log->description,
            'summary' => $log->summary(),
            'properties' => $log->properties,
            'ip_address' => $log->ip_address,

            'created_at' => $createdAt?->toIso8601String(),
            'created_at_human' => $createdAt?->diffForHumans(),
            'date' => $createdAt?->format('d-m-Y'),
            'time' => $createdAt?->format('h:i A'),
        ];
    }
}
