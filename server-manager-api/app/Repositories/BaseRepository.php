<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\RepositoryInterface;
use App\Support\QueryOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent implementation of the repository contract.
 *
 * Concrete repositories declare their model and, where needed, override
 * `applyFilters()` to translate request filters into query constraints. Search,
 * sorting, eager loading, pagination and soft-delete handling are inherited.
 *
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @return class-string<TModel>
     */
    abstract public function modelClass(): string;

    /**
     * Relations eager loaded by default on list/detail reads, to avoid N+1.
     *
     * @return array<int, string>
     */
    protected function defaultRelations(): array
    {
        return [];
    }

    /**
     * @return TModel
     */
    public function newModel(): Model
    {
        $class = $this->modelClass();

        return new $class;
    }

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->newModel()->newQuery();
    }

    /**
     * Compose the full read query: soft deletes, eager loads, filters, search,
     * then sort.
     *
     * @return Builder<TModel>
     */
    protected function buildQuery(QueryOptions $options): Builder
    {
        $query = $this->query();

        if ($options->withTrashed && $this->usesSoftDeletes()) {
            /** @phpstan-ignore-next-line withTrashed exists on soft-deleting models */
            $query->withTrashed();
        }

        $relations = $options->with === [] ? $this->defaultRelations() : $options->with;

        if ($relations !== []) {
            $query->with($relations);
        }

        $this->applyFilters($query, $options);

        if ($options->search !== null && method_exists($this->newModel(), 'scopeSearch')) {
            $query->search($options->search);
        }

        if (method_exists($this->newModel(), 'scopeSorted')) {
            $query->sorted($options->sort, $options->direction);
        }

        return $query;
    }

    /**
     * Translate `$options->filters` into query constraints.
     *
     * The base implementation applies exact matches for any filter key that is
     * a real column on the model, which covers `status`, `client_id`, etc.
     * Override to add ranges or relation-aware filters.
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyFilters(Builder $query, QueryOptions $options): void
    {
        foreach ($options->filters as $key => $value) {
            if (! in_array($key, $this->filterableColumns(), true)) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($query->qualifyColumn($key), $value);

                continue;
            }

            $query->where($query->qualifyColumn($key), $value);
        }
    }

    /**
     * Columns eligible for exact-match filtering. Allow-list: keys outside it
     * are ignored rather than injected into SQL.
     *
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return [];
    }

    /**
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(QueryOptions $options): LengthAwarePaginator
    {
        return $this->buildQuery($options)->paginate(
            perPage: $options->perPage,
            page: $options->page,
        );
    }

    /**
     * @return Collection<int, TModel>
     */
    public function get(QueryOptions $options): Collection
    {
        return $this->buildQuery($options)->get();
    }

    /**
     * @param  array<int, string>  $with
     * @return TModel|null
     */
    public function find(int $id, array $with = []): ?Model
    {
        return $this->query()
            ->with($with === [] ? $this->defaultRelations() : $with)
            ->find($id);
    }

    /**
     * @param  array<int, string>  $with
     * @return TModel
     */
    public function findOrFail(int $id, array $with = []): Model
    {
        return $this->query()
            ->with($with === [] ? $this->defaultRelations() : $with)
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        $model = $this->newModel();
        $model->fill($attributes);
        $model->save();

        return $model->refresh();
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes);
        $model->save();

        return $model->refresh();
    }

    /**
     * @param  TModel  $model
     */
    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    /**
     * @param  TModel  $model
     */
    public function restore(Model $model): bool
    {
        if (! $this->usesSoftDeletes()) {
            return false;
        }

        /** @phpstan-ignore-next-line restore exists on soft-deleting models */
        return (bool) $model->restore();
    }

    /**
     * @param  TModel  $model
     */
    public function forceDelete(Model $model): bool
    {
        return (bool) $model->forceDelete();
    }

    public function count(?QueryOptions $options = null): int
    {
        return $options === null
            ? $this->query()->count()
            : $this->buildQuery($options)->count();
    }

    protected function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->modelClass()), true);
    }
}
