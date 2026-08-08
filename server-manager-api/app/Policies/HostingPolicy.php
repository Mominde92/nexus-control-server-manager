<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Hosting;
use App\Models\User;

/**
 * Hosting authorisation.
 *
 * Admins never reach these methods: `Gate::before` in AuthServiceProvider
 * short-circuits every ability for them. What follows therefore expresses only
 * the Manager/Employee rules, driven by the granular permissions each role is
 * granted in RoleSeeder.
 *
 * Discovered automatically by Laravel's {Model}Policy convention.
 */
class HostingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewHosting->value);
    }

    public function view(User $user, Hosting $hosting): bool
    {
        return $user->can(Permission::ViewHosting->value) && $hosting->allowsUser($user, 'can_view');
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateHosting->value);
    }

    public function update(User $user, Hosting $hosting): bool
    {
        return $user->can(Permission::UpdateHosting->value) && $hosting->allowsUser($user, 'can_update');
    }

    public function delete(User $user, Hosting $hosting): bool
    {
        return $user->can(Permission::DeleteHosting->value) && $hosting->allowsUser($user, 'can_delete');
    }

    /**
     * Undoing a delete is a delete-grade action, not an edit.
     */
    public function restore(User $user, Hosting $hosting): bool
    {
        unset($hosting);

        return $user->can(Permission::DeleteHosting->value);
    }

    /**
     * Irreversible destruction is Admin-only, and Admins are already allowed by
     * `Gate::before` — so no other role may ever pass.
     */
    public function forceDelete(User $user, Hosting $hosting): bool
    {
        unset($user, $hosting);

        return false;
    }

    public function export(User $user): bool
    {
        return $user->can(Permission::ExportHosting->value);
    }

    /**
     * Reading a stored secret is deliberately a separate permission: view
     * access to the record never implies access to its credentials.
     */
    public function viewCredentials(User $user, Hosting $hosting): bool
    {
        return $user->can(Permission::ViewCredential->value) && $hosting->allowsUser($user, 'can_view_credentials');
    }
}
