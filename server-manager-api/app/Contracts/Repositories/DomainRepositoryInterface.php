<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Domain persistence contract.
 *
 * @extends RepositoryInterface<Domain>
 */
interface DomainRepositoryInterface extends RepositoryInterface
{
    /**
     * Domains expiring within the next $days days, soonest first.
     *
     * @param  int  $limit  0 = no limit.
     * @return Collection<int, Domain>
     */
    public function expiringWithin(int $days, int $limit = 0): Collection;

    /**
     * Fetch a record including soft-deleted rows, for the restore flow.
     *
     * @return Domain
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findWithTrashed(int $id): Model;

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array;

    public function totalCount(): int;

    public function activeCount(): int;

    public function expiringCount(int $days): int;

    public function expiredCount(): int;

    /**
     * @return Collection<int, Domain>
     */
    public function dueForNotification(int $horizon = 365): Collection;

    /**
     * Case-insensitive lookup by domain name. Domain names are compared
     * case-insensitively by DNS, so uniqueness checks must be too.
     */
    public function findByName(string $name): ?Domain;
}
