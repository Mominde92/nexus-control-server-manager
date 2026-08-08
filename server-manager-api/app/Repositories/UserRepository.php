<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Support\QueryOptions;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * @return class-string<User>
     */
    public function modelClass(): string
    {
        return User::class;
    }

    /**
     * `roles` powers the role badge; `roles.permissions` and the direct
     * `permissions` pivot are both required because Spatie's
     * `getAllPermissions()` touches all three — and lazy loading is disabled
     * outside production, so anything the Resource reads must be eager loaded.
     *
     * @return array<int, string>
     */
    protected function defaultRelations(): array
    {
        return [
            'roles:id,name',
            'roles.permissions:id,name',
            'permissions:id,name',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return ['status'];
    }

    /**
     * Adds two relation-aware filters on top of the base column matching:
     *
     *   ?role=manager        — users holding a role (Spatie's `role()` scope)
     *   ?permission=user.create — users with an ability, direct or via a role
     *
     * @param  Builder<User>  $query
     */
    protected function applyFilters(Builder $query, QueryOptions $options): void
    {
        parent::applyFilters($query, $options);

        $role = $options->filter('role');

        if (is_string($role) && $role !== '') {
            /** @phpstan-ignore-next-line role() is provided by Spatie's HasRoles */
            $query->role($role);
        }

        $permission = $options->filter('permission');

        if (is_string($permission) && $permission !== '') {
            /** @phpstan-ignore-next-line permission() is provided by Spatie's HasPermissions */
            $query->permission($permission);
        }
    }

    /**
     * @return array<string, int>
     */
    public function countByRole(): array
    {
        /** @var array<string, int> */
        return User::query()
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', (new User)->getMorphClass());
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->selectRaw('roles.name as role_name, COUNT(*) as aggregate')
            ->groupBy('roles.name')
            ->pluck('aggregate', 'role_name')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    public function countWithRole(string $role): int
    {
        /** @phpstan-ignore-next-line role() is provided by Spatie's HasRoles */
        return User::query()->role($role)->count();
    }

    public function findByEmail(string $email, bool $withTrashed = false): ?User
    {
        $query = User::query()->with($this->defaultRelations())->where('email', $email);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->first();
    }

    public function findWithTrashedOrFail(int $id): User
    {
        return User::query()
            ->with($this->defaultRelations())
            ->withTrashed()
            ->findOrFail($id);
    }
}
