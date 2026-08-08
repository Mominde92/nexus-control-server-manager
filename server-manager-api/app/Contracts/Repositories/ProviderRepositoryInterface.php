<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Provider;

/**
 * Persistence contract for vendor/provider records.
 *
 * @extends RepositoryInterface<Provider>
 */
interface ProviderRepositoryInterface extends RepositoryInterface
{
    /**
     * Provider count keyed by `ProviderType` value — feeds the dashboard
     * breakdown without pulling every row into memory.
     *
     * @return array<string, int>
     */
    public function countByType(): array;

    /**
     * How many providers are currently active.
     */
    public function activeCount(): int;

    /**
     * Dependent record counts (`hostings`, `domains`) referencing this
     * provider. Used to block a destructive delete.
     *
     * @return array<string, int>
     */
    public function dependentCounts(Provider $provider): array;

    /**
     * Fetch including soft-deleted rows — used by the restore endpoint.
     */
    public function findWithTrashedOrFail(int $id): Provider;
}
