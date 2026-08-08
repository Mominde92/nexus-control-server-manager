<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Support\QueryOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistence contract shared by every repository.
 *
 * Controllers and services depend on this abstraction, never on Eloquent
 * directly — which is what makes the data layer swappable and testable.
 *
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /**
     * Fully-qualified model class this repository manages.
     *
     * @return class-string<TModel>
     */
    public function modelClass(): string;

    /**
     * A filtered, sorted page of records.
     *
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(QueryOptions $options): LengthAwarePaginator;

    /**
     * Every matching record, unpaginated. Used by exports.
     *
     * @return Collection<int, TModel>
     */
    public function get(QueryOptions $options): Collection;

    /**
     * @param  array<int, string>  $with
     * @return TModel|null
     */
    public function find(int $id, array $with = []): ?Model;

    /**
     * @param  array<int, string>  $with
     * @return TModel
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id, array $with = []): Model;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model;

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model;

    /**
     * Soft delete when the model supports it, hard delete otherwise.
     *
     * @param  TModel  $model
     */
    public function delete(Model $model): bool;

    /**
     * @param  TModel  $model
     */
    public function restore(Model $model): bool;

    /**
     * @param  TModel  $model
     */
    public function forceDelete(Model $model): bool;

    public function count(?QueryOptions $options = null): int;
}
