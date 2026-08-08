<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ProviderRepositoryInterface;
use App\Enums\ProviderStatus;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Provider>
 */
class ProviderRepository extends BaseRepository implements ProviderRepositoryInterface
{
    /**
     * @return class-string<Provider>
     */
    public function modelClass(): string
    {
        return Provider::class;
    }

    /**
     * Providers have no parent to eager load; the list screen instead needs the
     * usage counters, which are added in `query()` as aggregate subqueries.
     *
     * @return array<int, string>
     */
    protected function defaultRelations(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return ['type', 'status'];
    }

    /**
     * Attach `hostings_count` / `domains_count` to every read. Cheaper than
     * loading the relations, and the Resource exposes them via whenCounted().
     *
     * @return Builder<Provider>
     */
    public function query(): Builder
    {
        return parent::query()->withCount(['hostings', 'domains']);
    }

    /**
     * @return array<string, int>
     */
    public function countByType(): array
    {
        /** @var array<string, int> */
        return Provider::query()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    public function activeCount(): int
    {
        return Provider::query()->where('status', ProviderStatus::Active->value)->count();
    }

    /**
     * @return array<string, int>
     */
    public function dependentCounts(Provider $provider): array
    {
        return [
            'hostings' => $provider->hostings()->count(),
            'domains' => $provider->domains()->count(),
        ];
    }

    public function findWithTrashedOrFail(int $id): Provider
    {
        return $this->query()
            ->withTrashed()
            ->findOrFail($id);
    }
}
