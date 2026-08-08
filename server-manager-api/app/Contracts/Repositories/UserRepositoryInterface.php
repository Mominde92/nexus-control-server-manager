<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;

/**
 * Persistence contract for user accounts.
 *
 * @extends RepositoryInterface<User>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * User count keyed by role name (`admin`, `manager`, `employee`).
     *
     * @return array<string, int>
     */
    public function countByRole(): array;

    /**
     * How many non-deleted users currently hold the given role.
     */
    public function countWithRole(string $role): int;

    /**
     * Locate an account by email, including soft-deleted rows when asked.
     */
    public function findByEmail(string $email, bool $withTrashed = false): ?User;

    /**
     * Fetch including soft-deleted rows — used by the restore endpoint.
     */
    public function findWithTrashedOrFail(int $id): User;
}
