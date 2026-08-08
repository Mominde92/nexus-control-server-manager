<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Enums\UserStatus;
use App\Models\User;

/**
 * Application service for the User module.
 *
 * Beyond CRUD this service owns the account-safety rules: privilege escalation
 * prevention, last-admin protection and token revocation.
 *
 * @extends CrudServiceInterface<User>
 */
interface UserServiceInterface extends CrudServiceInterface
{
    /**
     * Fetch including soft-deleted rows so the restore endpoint can authorise
     * against a real model instance.
     */
    public function findWithTrashed(int $id): User;

    /**
     * Activate / deactivate / suspend an account. Any non-active status revokes
     * every API token the user holds, so a suspension takes effect immediately
     * rather than at token expiry.
     */
    public function changeStatus(User $user, UserStatus $status): User;

    /**
     * User counts keyed by role name.
     *
     * @return array<string, int>
     */
    public function countByRole(): array;
}
