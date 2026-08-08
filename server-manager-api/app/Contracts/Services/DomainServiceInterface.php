<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Collection;

/**
 * Domain business rules.
 *
 * @extends CrudServiceInterface<Domain>
 */
interface DomainServiceInterface extends CrudServiceInterface
{
    /**
     * Decrypted registrar credentials for a single record. Writes a
     * `credential_viewed` audit entry as a side effect.
     *
     * @return array<string, string|null>
     */
    public function revealCredentials(Domain $domain): array;

    /**
     * Fetch a record including soft-deleted rows, for the restore endpoint.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findTrashed(int $id): Domain;

    /**
     * @return array{total: int, active: int, expiring: int, expired: int, by_status: array<string, int>}
     */
    public function statistics(): array;

    /**
     * @return Collection<int, Domain>
     */
    public function expiringWithin(int $days = 30, int $limit = 0): Collection;

    /**
     * Case-insensitive lookup by domain name.
     */
    public function findByName(string $name): ?Domain;

    public function forceDelete(Domain $domain): bool;
}
