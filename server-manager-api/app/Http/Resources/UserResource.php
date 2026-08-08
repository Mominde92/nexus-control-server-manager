<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsEnums;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a user account.
 *
 * `password` and `remember_token` are never emitted — they are not listed here,
 * and `User::$hidden` is a second line of defence.
 *
 * The `permissions` array is the *effective* set (direct grants plus everything
 * inherited from the role), flattened to plain strings so the SPA can gate a
 * button with a single `includes()` check. UserRepository eager loads `roles`,
 * `roles.permissions` and `permissions` precisely so building it never triggers
 * a lazy-load violation.
 *
 * @mixin User
 */
class UserResource extends JsonResource
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
            'job_title' => $this->job_title,
            'avatar_path' => $this->avatar_path,

            'status' => $this->enumPayload($this->status),
            'role' => $this->whenLoaded('roles', function (): ?array {
                $name = $this->roles->first()?->name;
                return $name === null ? null : ['value' => $name, 'label' => ucwords(str_replace(['-', '_'], ' ', $name))];
            }),

            // Requires `permissions` *and* `roles.permissions`: Spatie merges the
            // direct pivot with the role-inherited set, touching both relations.
            'permissions' => $this->when(
                $this->resource->relationLoaded('roles') && $this->resource->relationLoaded('permissions'),
                fn (): array => $this->getAllPermissions()->pluck('name')->values()->all(),
            ),

            'email_verified_at' => $this->isoDate($this->email_verified_at),
            'last_login_at' => $this->isoDate($this->last_login_at),

            'created_at' => $this->isoDate($this->created_at),
            'updated_at' => $this->isoDate($this->updated_at),
            'deleted_at' => $this->isoDate($this->deleted_at),
        ];
    }
}
