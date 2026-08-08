<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated identity, as the SPA needs it: profile fields plus the flat
 * permission-name list the client uses to show or hide UI.
 *
 * Callers MUST eager load `roles.permissions` before serialising — lazy loading
 * is disabled outside production, so a missing eager load fails fast here rather
 * than quietly issuing N+1 queries in production.
 *
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    /**
     * Relations this resource reads. Spatie resolves abilities from BOTH the
     * user's roles and any directly-assigned permissions, so both sides must be
     * loaded or `getAllPermissions()` would lazy load.
     *
     * @return array<int, string>
     */
    public static function relations(): array
    {
        return ['roles.permissions', 'permissions'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($request);

        /** @var User $user */
        $user = $this->resource;

        $status = $user->status instanceof UserStatus ? $user->status : null;
        $role = $user->primaryRole();
        $roleName = $user->roles->first()?->name;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'avatar_path' => $user->avatar_path,

            'status' => $status === null ? null : [
                'value' => $status->value,
                'label' => $status->label(),
                'variant' => $status->variant(),
            ],

            'role' => $roleName === null ? null : [
                'value' => $roleName,
                'label' => $role?->label() ?? ucwords(str_replace(['-', '_'], ' ', $roleName)),
            ],

            // Flattened across the user's roles: the client only ever asks
            // "may I?", never "through which role?".
            'permissions' => $user->getAllPermissions()
                ->pluck('name')
                ->unique()
                ->sort()
                ->values()
                ->all(),

            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
