<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Generic keyword search over an allow-listed set of columns.
 *
 * Implementers declare:
 *   public const SEARCHABLE = ['name', 'email'];          // matched with LIKE
 *   public const SORTABLE   = ['id', 'name', 'created_at']; // sort allow-list
 *
 * Both lists are allow-lists: user input is never interpolated into SQL
 * identifiers, which is what keeps `?search=` and `?sort=` injection-safe.
 */
trait Searchable
{
    /**
     * Columns eligible for LIKE matching.
     *
     * @return array<int, string>
     */
    public static function searchableColumns(): array
    {
        /** @var array<int, string> */
        return defined(static::class.'::SEARCHABLE') ? static::SEARCHABLE : [];
    }

    /**
     * Columns eligible for ORDER BY.
     *
     * @return array<int, string>
     */
    public static function sortableColumns(): array
    {
        /** @var array<int, string> */
        return defined(static::class.'::SORTABLE') ? static::SORTABLE : ['id'];
    }

    /**
     * Apply a free-text search across `SEARCHABLE`, including a couple of
     * related columns where the model opts in via `SEARCHABLE_RELATIONS`.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = $term === null ? '' : trim($term);

        if ($term === '') {
            return $query;
        }

        // Escape LIKE wildcards so a literal % or _ is not treated as a pattern.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        $like = '%'.$escaped.'%';

        $columns = static::searchableColumns();

        /** @var array<string, array<int, string>> $relations */
        $relations = defined(static::class.'::SEARCHABLE_RELATIONS')
            ? static::SEARCHABLE_RELATIONS
            : [];

        return $query->where(function (Builder $inner) use ($columns, $relations, $like): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', $like);
            }

            foreach ($relations as $relation => $relationColumns) {
                $inner->orWhereHas($relation, function (Builder $related) use ($relationColumns, $like): void {
                    $related->where(function (Builder $group) use ($relationColumns, $like): void {
                        foreach ($relationColumns as $column) {
                            $group->orWhere($column, 'like', $like);
                        }
                    });
                });
            }
        });
    }

    /**
     * Apply an allow-listed sort, falling back to a stable default.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSorted(Builder $query, ?string $column, ?string $direction = 'asc'): Builder
    {
        $allowed = static::sortableColumns();
        $column = in_array($column, $allowed, true) ? $column : ($allowed[0] ?? 'id');
        $direction = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($query->qualifyColumn($column), $direction)
            // Tie-breaker keeps pagination deterministic when the sort column
            // has duplicate values.
            ->orderBy($query->qualifyColumn('id'), 'desc');
    }
}
