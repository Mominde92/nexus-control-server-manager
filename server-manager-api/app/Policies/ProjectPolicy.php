<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;

/**
 * Authorisation for the Project module. Admins bypass via `Gate::before`.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewProject->value);
    }

    public function view(User $user, Project $project): bool
    {
        unset($project);

        return $user->can(Permission::ViewProject->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateProject->value);
    }

    public function update(User $user, Project $project): bool
    {
        unset($project);

        return $user->can(Permission::UpdateProject->value);
    }

    public function delete(User $user, Project $project): bool
    {
        unset($project);

        return $user->can(Permission::DeleteProject->value);
    }

    public function restore(User $user, Project $project): bool
    {
        unset($project);

        return $user->can(Permission::DeleteProject->value);
    }

    /**
     * Irreversible. Reserved for Admins via `Gate::before`.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        unset($user, $project);

        return false;
    }

    public function export(User $user): bool
    {
        return $user->can(Permission::ExportProject->value);
    }
}
