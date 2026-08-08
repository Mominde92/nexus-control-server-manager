<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ClientRepositoryInterface;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Client>
 */
class ClientRepository extends BaseRepository implements ClientRepositoryInterface
{
    /**
     * @return class-string<Client>
     */
    public function modelClass(): string
    {
        return Client::class;
    }

    /**
     * Clients are a root entity — nothing to eager load, only counters.
     *
     * @return array<int, string>
     */
    protected function defaultRelations(): array
    {
        return [];
    }

    /**
     * Clients are filtered by free-text search only; there is no status column.
     *
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return [];
    }

    /**
     * @return Builder<Client>
     */
    public function query(): Builder
    {
        return parent::query()->withCount(['projects', 'hostings', 'domains']);
    }

    /**
     * @return array<string, int>
     */
    public function dependentCounts(Client $client): array
    {
        return [
            'projects' => $client->projects()->count(),
            'hostings' => $client->hostings()->count(),
            'domains' => $client->domains()->count(),
        ];
    }

    public function findWithTrashedOrFail(int $id): Client
    {
        return $this->query()
            ->withTrashed()
            ->findOrFail($id);
    }
}
