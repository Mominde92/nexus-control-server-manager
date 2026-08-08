<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\UserServiceInterface;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Concerns\NormalisesInput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Business rules for the User module.
 *
 * This service owns the three account-safety invariants the rest of the app
 * relies on:
 *
 *  1. **No privilege escalation** — a non-Admin may never mint or promote an
 *     account at or above their own rank, which would otherwise let a Manager
 *     create an Admin and inherit the whole system.
 *  2. **At least one Admin always exists** — locking everyone out is
 *     unrecoverable without database access.
 *  3. **Losing access is immediate** — deleting or deactivating an account
 *     revokes its Sanctum tokens rather than waiting for them to expire.
 *
 * @extends BaseCrudService<User>
 */
class UserService extends BaseCrudService implements UserServiceInterface
{
    use NormalisesInput;

    /**
     * Relations the API Resource reads. Reloaded explicitly after a role change
     * because lazy loading is disabled outside production.
     *
     * @var array<int, string>
     */
    private const array RESOURCE_RELATIONS = [
        'roles:id,name',
        'roles.permissions:id,name',
        'permissions:id,name',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
        parent::__construct($users);
    }

    public function findWithTrashed(int $id): User
    {
        return $this->users->findWithTrashedOrFail($id);
    }

    /**
     * @return array<string, int>
     */
    public function countByRole(): array
    {
        return $this->users->countByRole();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $role = $this->extractRole($data);
        $roleName = is_string($data['role'] ?? null) ? $data['role'] : null;

        $this->assertMayAssign($role);

        return DB::transaction(function () use ($data, $role, $roleName): User {
            /** @var User $user */
            $user = parent::create($data);

            if ($roleName !== null) {
                $user->syncRoles([$roleName]);
            }

            return $user->load(self::RESOURCE_RELATIONS);
        });
    }

    /**
     * @param  User  $model
     * @param  array<string, mixed>  $data
     */
    public function update(Model $model, array $data): Model
    {
        $role = $this->extractRole($data);
        $roleName = is_string($data['role'] ?? null) ? $data['role'] : null;

        // Guard both ends: the actor must outrank who the user *is* today and
        // who they would *become*.
        $this->assertMayAdminister($model);
        $this->assertMayAssign($role);

        return DB::transaction(function () use ($model, $data, $role, $roleName): User {
            if ($role instanceof UserRole && $role !== $model->loadMissing('roles:id,name')->primaryRole()) {
                $this->assertNotDemotingLastAdmin($model, $role);
            }

            /** @var User $user */
            $user = parent::update($model, $data);

            if ($roleName !== null) {
                $user->syncRoles([$roleName]);
            }

            return $user->load(self::RESOURCE_RELATIONS);
        });
    }

    /**
     * @param  User  $model
     */
    public function delete(Model $model): bool
    {
        $actor = $this->actor();

        if ($actor !== null && $actor->is($model)) {
            throw new AuthorizationException('You cannot delete your own account.');
        }

        $this->assertMayAdminister($model);
        $this->assertNotLastAdmin($model);

        return DB::transaction(function () use ($model): bool {
            // Revoke first: if the delete fails the transaction rolls the token
            // removal back too, so the two can never disagree.
            $model->tokens()->delete();

            return parent::delete($model);
        });
    }

    /**
     * Activate, deactivate or suspend an account.
     *
     * Any non-active status revokes every token the user holds, so a suspension
     * takes effect on the next request rather than at token expiry.
     */
    public function changeStatus(User $user, UserStatus $status): User
    {
        $this->assertMayAdminister($user);

        if ($status !== UserStatus::Active) {
            $actor = $this->actor();

            if ($actor !== null && $actor->is($user)) {
                throw new AuthorizationException('You cannot deactivate your own account.');
            }

            $this->assertNotLastAdmin($user);
        }

        return DB::transaction(function () use ($user, $status): User {
            /** @var User $updated */
            $updated = $this->users->update($user, ['status' => $status->value]);

            if (! $status->canAuthenticate()) {
                $updated->tokens()->delete();
            }

            return $updated->load(self::RESOURCE_RELATIONS);
        });
    }

    // -----------------------------------------------------------------------
    // Lifecycle hooks
    // -----------------------------------------------------------------------

    /**
     * `role` is deliberately absent from `User::$fillable` — it lives in
     * Spatie's pivot table, not on the row. With
     * `preventSilentlyDiscardingAttributes()` enabled, leaving it in the payload
     * would throw, so it is stripped here and applied via `syncRoles()`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        unset($data['role'], $data['password_confirmation']);

        $password = $data['password'] ?? null;
        unset($data['password']);

        $data = $this->trimAndNullify($data);
        $data = $this->lowercaseKeys($data, ['email']);

        // Restored last so trimming never mangles a password's leading or
        // trailing whitespace, which is significant.
        if (is_string($password) && $password !== '') {
            // The `hashed` cast on User::$casts does the hashing on assignment.
            $data['password'] = $password;
        }

        return $data;
    }

    /**
     * On update an absent or blank password means "leave it alone" — `prepare()`
     * already dropped it, so the existing hash survives untouched.
     *
     * @param  array<string, mixed>  $data
     * @param  User  $model
     * @return array<string, mixed>
     */
    protected function prepareForUpdate(array $data, Model $model): array
    {
        $prepared = $this->prepare($data);

        // Defensive: never write a null/empty password over a valid hash.
        if (($prepared['password'] ?? null) === null) {
            unset($prepared['password']);
        }

        return $prepared;
    }

    // -----------------------------------------------------------------------
    // Guards
    // -----------------------------------------------------------------------

    /**
     * The authenticated actor, with roles guaranteed present.
     *
     * `loadMissing()` is explicit eager loading, so it satisfies
     * `preventLazyLoading()` — the session user arrives from Sanctum without
     * relations, and every guard below reads its role.
     */
    private function actor(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->loadMissing('roles:id,name');
    }

    /**
     * Pull `role` out of the payload and resolve it to an enum case.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractRole(array $data): ?UserRole
    {
        $role = $data['role'] ?? null;

        if ($role instanceof UserRole) {
            return $role;
        }

        return is_string($role) ? UserRole::tryFrom($role) : null;
    }

    /**
     * A non-Admin actor may only assign roles strictly below their own.
     */
    private function assertMayAssign(?UserRole $role): void
    {
        if ($role === null) {
            return;
        }

        $actor = $this->actor();

        // No session: console commands, seeders and queued jobs are trusted.
        if ($actor === null || $actor->isAdmin()) {
            return;
        }

        $actorRole = $actor->primaryRole();

        if ($actorRole === null || ! $actorRole->outranks($role)) {
            throw new AuthorizationException(sprintf(
                'You may not assign the "%s" role, which is at or above your own (%s).',
                $role->label(),
                $actorRole?->label() ?? 'none',
            ));
        }
    }

    /**
     * A non-Admin actor may only modify accounts they outrank.
     */
    private function assertMayAdminister(User $target): void
    {
        $actor = $this->actor();

        if ($actor === null || $actor->isAdmin() || $actor->is($target)) {
            return;
        }

        $target->loadMissing('roles:id,name');

        if (! $actor->outranks($target)) {
            throw new AuthorizationException(sprintf(
                'You may not manage "%s", whose role is at or above your own.',
                $target->name,
            ));
        }
    }

    /**
     * Deleting or suspending the only Admin would lock everyone out of user
     * management permanently.
     */
    private function assertNotLastAdmin(User $target): void
    {
        if ($target->loadMissing('roles:id,name')->primaryRole() !== UserRole::Admin) {
            return;
        }

        if ($this->users->countWithRole(UserRole::Admin->value) <= 1) {
            throw new RuntimeException(
                'This is the only Admin account — promote another user to Admin before removing or disabling it.'
            );
        }
    }

    private function assertNotDemotingLastAdmin(User $target, UserRole $newRole): void
    {
        if ($newRole === UserRole::Admin) {
            return;
        }

        $this->assertNotLastAdmin($target);
    }
}
