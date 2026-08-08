<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;

/**
 * Authorisation for the Client module. Admins bypass via `Gate::before`.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewClient->value);
    }

    public function view(User $user, Client $client): bool
    {
        unset($client);

        return $user->can(Permission::ViewClient->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateClient->value);
    }

    public function update(User $user, Client $client): bool
    {
        unset($client);

        return $user->can(Permission::UpdateClient->value);
    }

    public function delete(User $user, Client $client): bool
    {
        unset($client);

        return $user->can(Permission::DeleteClient->value);
    }

    public function restore(User $user, Client $client): bool
    {
        unset($client);

        return $user->can(Permission::DeleteClient->value);
    }

    /**
     * Irreversible. Reserved for Admins via `Gate::before`.
     */
    public function forceDelete(User $user, Client $client): bool
    {
        unset($user, $client);

        return false;
    }

    public function export(User $user): bool
    {
        return $user->can(Permission::ExportClient->value);
    }
}
