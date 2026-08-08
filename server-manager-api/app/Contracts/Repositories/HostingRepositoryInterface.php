<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Hosting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Hosting persistence contract.
 *
 * Adds the expiry-oriented reads the dashboard and the expiry-reminder command
 * need, on top of the generic CRUD surface.
 *
 * @extends RepositoryInterface<Hosting>
 */
interface HostingRepositoryInterface extends RepositoryInterface
{
    /**
     * Hostings expiring within the next $days days, soonest first.
     *
     * @param  int  $limit  0 = no limit.
     * @return Collection<int, Hosting>
     */
    public function expiringWithin(int $days, int $limit = 0): Collection;

    /**
     * Fetch a record including soft-deleted rows, for the restore flow.
     *
     * @return Hosting
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findWithTrashed(int $id): Model;

    /**
     * Record counts keyed by status value, resolved in a single grouped query.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array;

    public function totalCount(): int;

    public function activeCount(): int;

    public function expiringCount(int $days): int;

    public function expiredCount(): int;

    /**
     * Records eligible for an expiry reminder, already narrowed by each
     * record's own notification window.
     *
     * @return Collection<int, Hosting>
     */
    public function dueForNotification(int $horizon = 365): Collection;
}
