<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\RepositoryInterface;
use App\Contracts\Services\CrudServiceInterface;
use App\Support\QueryOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Transactional CRUD orchestration shared by every module service.
 *
 * Writes run inside a database transaction, so a failure in a lifecycle hook
 * (or in the audit write) rolls the whole operation back rather than leaving a
 * half-applied record.
 *
 * Subclasses override the `prepare*` / `after*` hooks to add business rules
 * without re-implementing the transaction plumbing.
 *
 * @template TModel of Model
 *
 * @implements CrudServiceInterface<TModel>
 */
abstract class BaseCrudService implements CrudServiceInterface
{
    /**
     * @param  RepositoryInterface<TModel>  $repository
     */
    public function __construct(
        protected readonly RepositoryInterface $repository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(QueryOptions $options): LengthAwarePaginator
    {
        return $this->repository->paginate($options);
    }

    /**
     * @return Collection<int, TModel>
     */
    public function all(QueryOptions $options): Collection
    {
        return $this->repository->get($options);
    }

    /**
     * @return TModel
     */
    public function find(int $id): Model
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $model = $this->repository->create($this->prepareForCreate($data));

            $this->afterCreate($model, $data);

            return $model;
        });
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function update(Model $model, array $data): Model
    {
        return DB::transaction(function () use ($model, $data): Model {
            $updated = $this->repository->update($model, $this->prepareForUpdate($data, $model));

            $this->afterUpdate($updated, $data);

            return $updated;
        });
    }

    /**
     * @param  TModel  $model
     */
    public function delete(Model $model): bool
    {
        return DB::transaction(fn (): bool => $this->repository->delete($model));
    }

    /**
     * @param  TModel  $model
     */
    public function restore(Model $model): bool
    {
        return DB::transaction(fn (): bool => $this->repository->restore($model));
    }

    /**
     * @param  TModel  $model
     */
    public function forceDelete(Model $model): bool
    {
        return DB::transaction(fn (): bool => $this->repository->forceDelete($model));
    }

    // -----------------------------------------------------------------------
    // Lifecycle hooks
    // -----------------------------------------------------------------------

    /**
     * Normalise validated input before insert.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareForCreate(array $data): array
    {
        return $this->prepare($data);
    }

    /**
     * Normalise validated input before update.
     *
     * @param  array<string, mixed>  $data
     * @param  TModel  $model
     * @return array<string, mixed>
     */
    protected function prepareForUpdate(array $data, Model $model): array
    {
        unset($model);

        return $this->prepare($data);
    }

    /**
     * Shared normalisation applied to both create and update.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        return $data;
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     */
    protected function afterCreate(Model $model, array $data): void
    {
        unset($model, $data);
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     */
    protected function afterUpdate(Model $model, array $data): void
    {
        unset($model, $data);
    }
}
