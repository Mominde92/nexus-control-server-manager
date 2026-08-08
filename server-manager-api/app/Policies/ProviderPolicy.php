<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Provider;
use App\Models\User;

/**
 * Authorisation for the Provider module.
 *
 * Admins never reach these methods — `Gate::before` in AuthServiceProvider short
 * circuits every ability for them. So each rule below expresses only what a
 * Manager or Employee may do, and `forceDelete` returning `false` reads as
 * "Admin only".
 */
class ProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewProvider->value);
    }

    public function view(User $user, Provider $provider): bool
    {
        unset($provider);

        return $user->can(Permission::ViewProvider->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateProvider->value);
    }

    public function update(User $user, Provider $provider): bool
    {
        unset($provider);

        return $user->can(Permission::UpdateProvider->value);
    }

    public function delete(User $user, Provider $provider): bool
    {
        unset($provider);

        return $user->can(Permission::DeleteProvider->value);
    }

    public function restore(User $user, Provider $provider): bool
    {
        unset($provider);

        // Bringing a record back is a form of deletion control, not creation.
        return $user->can(Permission::DeleteProvider->value);
    }

    /**
     * Irreversible. Reserved for Admins via `Gate::before`.
     */
    public function forceDelete(User $user, Provider $provider): bool
    {
        unset($user, $provider);

        return false;
    }

    public function export(User $user): bool
    {
        return $user->can(Permission::ExportProvider->value);
    }
}
