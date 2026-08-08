<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Authorisation for the User module — the one policy where the *target* matters.
 *
 * Holding `user.update` is not enough: the actor must also outrank the account
 * they are touching, otherwise a Manager with user permissions could edit an
 * Admin and escalate. The one deliberate exception is a user editing their own
 * profile, which nobody outranks.
 *
 * Admins bypass all of this via `Gate::before`.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewUser->value);
    }

    /**
     * Everyone may read their own record, even without `user.view`.
     */
    public function view(User $user, User $target): bool
    {
        return $user->is($target) || $user->can(Permission::ViewUser->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateUser->value);
    }

    public function update(User $user, User $target): bool
    {
        // Self-service profile editing needs no `user.update` grant.
        if ($user->is($target)) {
            return true;
        }

        return $user->can(Permission::UpdateUser->value)
            && $this->outranks($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        // Deleting yourself would strand the session and, if you are the last
        // Admin, the whole installation.
        if ($user->is($target)) {
            return false;
        }

        return $user->can(Permission::DeleteUser->value)
            && $this->outranks($user, $target);
    }

    public function restore(User $user, User $target): bool
    {
        return $user->can(Permission::DeleteUser->value)
            && $this->outranks($user, $target);
    }

    /**
     * Irreversible, and it erases the audit subject. Admin only, expressed as a
     * flat denial that `Gate::before` overrides.
     */
    public function forceDelete(User $user, User $target): bool
    {
        unset($user, $target);

        return false;
    }

    public function export(User $user): bool
    {
        return $user->can(Permission::ExportUser->value);
    }

    /**
     * Rank comparison with both sides' roles guaranteed present.
     *
     * `User::outranks()` reads `primaryRole()` on both models, and lazy loading
     * is disabled outside production — the actor arrives from the Sanctum guard
     * with no relations loaded, so both sides need an explicit load.
     */
    private function outranks(User $user, User $target): bool
    {
        return $user->loadMissing('roles:id,name')
            ->outranks($target->loadMissing('roles:id,name'));
    }

    /**
     * Activate / deactivate / suspend. Same rank rule as `update`, but never on
     * your own account — that is a lockout waiting to happen.
     */
    public function changeStatus(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return false;
        }

        return $user->can(Permission::UpdateUser->value)
            && $this->outranks($user, $target);
    }
}
