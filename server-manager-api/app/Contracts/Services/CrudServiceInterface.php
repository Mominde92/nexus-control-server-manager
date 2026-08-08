<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Support\QueryOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Application-service contract for a CRUD resource.
 *
 * Services own business rules and transaction boundaries; repositories own
 * persistence. Controllers talk only to services.
 *
 * @template TModel of Model
 */
interface CrudServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(QueryOptions $options): LengthAwarePaginator;

    /**
     * @return Collection<int, TModel>
     */
    public function all(QueryOptions $options): Collection;

    /**
     * @return TModel
     */
    public function find(int $id): Model;

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): Model;

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function update(Model $model, array $data): Model;

    /**
     * @param  TModel  $model
     */
    public function delete(Model $model): bool;

    /**
     * @param  TModel  $model
     */
    public function restore(Model $model): bool;
}
