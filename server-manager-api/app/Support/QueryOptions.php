<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Immutable value object describing a list request: pagination, keyword search,
 * sorting, eager loads and column filters.
 *
 * Built once in the controller (from the validated request) and handed to the
 * repository, so query-string parsing never leaks into the data layer.
 */
final readonly class QueryOptions
{
    public const int DEFAULT_PER_PAGE = 15;

    public const int MAX_PER_PAGE = 100;

    /**
     * @param  array<string, mixed>  $filters  Column => value (nulls stripped).
     * @param  array<int, string>  $with  Relations to eager load.
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public ?string $search = null,
        public ?string $sort = null,
        public string $direction = 'asc',
        public array $filters = [],
        public array $with = [],
        public bool $withTrashed = false,
    ) {}

    /**
     * Build from an HTTP request.
     *
     * @param  array<int, string>  $allowedFilters  Filter keys this endpoint accepts.
     * @param  array<int, string>  $with
     */
    public static function fromRequest(
        Request $request,
        array $allowedFilters = [],
        array $with = [],
    ): self {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $search = $request->string('search')->trim()->value();

        // Accept `?sort=-expiry_date` as shorthand for descending, as well as
        // the explicit `?sort=expiry_date&direction=desc` pair.
        $sort = $request->string('sort')->trim()->value();
        $direction = strtolower($request->string('direction', 'asc')->trim()->value());

        if (str_starts_with($sort, '-')) {
            $sort = substr($sort, 1);
            $direction = 'desc';
        }

        $filters = [];

        foreach ($allowedFilters as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $value = $request->input($key);

            // Treat empty string / "all" as "no filter" so the UI can send a
            // cleared select without special-casing.
            if ($value === null || $value === '' || $value === 'all') {
                continue;
            }

            $filters[$key] = $value;
        }

        return new self(
            page: max(1, (int) $request->integer('page', 1)),
            perPage: $perPage,
            search: $search === '' ? null : $search,
            sort: $sort === '' ? null : $sort,
            direction: $direction === 'desc' ? 'desc' : 'asc',
            filters: $filters,
            with: $with,
            withTrashed: $request->boolean('with_trashed'),
        );
    }

    public function filter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    public function hasFilter(string $key): bool
    {
        return array_key_exists($key, $this->filters);
    }

    /**
     * A copy with pagination removed — used by exports, which stream every
     * matching row rather than a page.
     */
    public function withoutPagination(): self
    {
        return new self(
            page: 1,
            perPage: PHP_INT_MAX,
            search: $this->search,
            sort: $this->sort,
            direction: $this->direction,
            filters: $this->filters,
            with: $this->with,
            withTrashed: $this->withTrashed,
        );
    }

    /**
     * @param  array<int, string>  $with
     */
    public function withRelations(array $with): self
    {
        return new self(
            page: $this->page,
            perPage: $this->perPage,
            search: $this->search,
            sort: $this->sort,
            direction: $this->direction,
            filters: $this->filters,
            with: $with,
            withTrashed: $this->withTrashed,
        );
    }
}
