<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Server;
use App\Models\User;

/**
 * Server authorisation.
 *
 * Admins never reach these methods: `Gate::before` in AuthServiceProvider
 * short-circuits every ability for them. What follows therefore expresses only
 * the Manager/Employee rules.
 *
 * Discovered automatically by Laravel's {Model}Policy convention.
 */
class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewServer->value);
    }

    public function view(User $user, Server $server): bool
    {
        unset($server);

        return $user->can(Permission::ViewServer->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateServer->value);
    }

    public function update(User $user, Server $server): bool
    {
        unset($server);

        return $user->can(Permission::UpdateServer->value);
    }

    public function delete(User $user, Server $server): bool
    {
        unset($server);

        return $user->can(Permission::DeleteServer->value);
    }

    /**
     * Undoing a delete is a delete-grade action, not an edit.
     */
    public function restore(User $user, Server $server): bool
    {
        unset($server);

        return $user->can(Permission::DeleteServer->value);
    }

    /**
     * Irreversible destruction takes the monitoring history with it, so it is
     * Admin-only — and Admins already pass via `Gate::before`.
     */
    public function forceDelete(User $user, Server $server): bool
    {
        unset($user, $server);

        return false;
    }

    public function export(User $user): bool
    {
        return $user->can(Permission::ExportServer->value);
    }

    /**
     * Minting an agent credential creates a bearer secret that grants ingest
     * access to a host. That is a security operation rather than an edit, so it
     * is Admin-only: `Gate::before` lets Admins through, and returning false here
     * means no other role can ever pass — not even one that was mistakenly
     * granted `server.agent.manage`.
     */
    public function manageAgent(User $user, Server $server): bool
    {
        unset($user, $server);

        return false;
    }

    public function viewMetrics(User $user, Server $server): bool
    {
        unset($server);

        return $user->can(Permission::ViewServerMetric->value);
    }

    public function manageAlerts(User $user): bool
    {
        return $user->can(Permission::ManageServerAlert->value);
    }
}
