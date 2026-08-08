<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Domain;
use App\Models\User;

/**
 * Domain authorisation. Admins bypass every check via `Gate::before`.
 */
class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewDomain->value);
    }

    public function view(User $user, Domain $domain): bool
    {
        return $user->can(Permission::ViewDomain->value) && $domain->allowsUser($user, 'can_view');
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateDomain->value);
    }

    public function update(User $user, Domain $domain): bool
    {
        return $user->can(Permission::UpdateDomain->value) && $domain->allowsUser($user, 'can_update');
    }

    public function delete(User $user, Domain $domain): bool
    {
        return $user->can(Permission::DeleteDomain->value) && $domain->allowsUser($user, 'can_delete');
    }

    public function restore(User $user, Domain $domain): bool
    {
        unset($domain);

        return $user->can(Permission::DeleteDomain->value);
    }

    /**
     * Admin-only, granted by `Gate::before`.
     */
    public function forceDelete(User $user, Domain $domain): bool
    {
        unset($user, $domain);

        return false;
    }

    public function export(User $user): bool
    {
        return $user->can(Permission::ExportDomain->value);
    }

    public function viewCredentials(User $user, Domain $domain): bool
    {
        return $user->can(Permission::ViewCredential->value) && $domain->allowsUser($user, 'can_view_credentials');
    }
}
