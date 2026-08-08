<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Client;

/**
 * Persistence contract for client records.
 *
 * @extends RepositoryInterface<Client>
 */
interface ClientRepositoryInterface extends RepositoryInterface
{
    /**
     * Dependent record counts (`projects`, `hostings`, `domains`) pointing at
     * this client. Used to block a destructive delete.
     *
     * @return array<string, int>
     */
    public function dependentCounts(Client $client): array;

    /**
     * Fetch including soft-deleted rows — used by the restore endpoint.
     */
    public function findWithTrashedOrFail(int $id): Client;
}
