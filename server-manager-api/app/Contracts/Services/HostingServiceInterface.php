<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Hosting;
use Illuminate\Database\Eloquent\Collection;

/**
 * Hosting business rules.
 *
 * @extends CrudServiceInterface<Hosting>
 */
interface HostingServiceInterface extends CrudServiceInterface
{
    /**
     * Decrypted vendor credentials for a single record.
     *
     * Writes a `credential_viewed` audit entry as a side effect — revealing a
     * secret is itself an auditable event. The caller must already have
     * authorised the `viewCredentials` ability.
     *
     * @return array<string, string|null>
     */
    public function revealCredentials(Hosting $hosting): array;

    /**
     * Fetch a record including soft-deleted rows. Route-model binding hides
     * trashed records, so the restore endpoint resolves the id itself.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findTrashed(int $id): Hosting;

    /**
     * Dashboard counters.
     *
     * @return array{total: int, active: int, expiring: int, expired: int, by_status: array<string, int>}
     */
    public function statistics(): array;

    /**
     * Records expiring within the given window, soonest first.
     *
     * @return Collection<int, Hosting>
     */
    public function expiringWithin(int $days = 30, int $limit = 0): Collection;

    /**
     * Permanently remove a record. Admin-only at the policy layer.
     */
    public function forceDelete(Hosting $hosting): bool;
}
