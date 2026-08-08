<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Project;

/**
 * Persistence contract for project records.
 *
 * @extends RepositoryInterface<Project>
 */
interface ProjectRepositoryInterface extends RepositoryInterface
{
    /**
     * Project count keyed by `ProjectStatus` value.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array;

    /**
     * Dependent record counts (`hostings`, `domains`) for this project.
     *
     * @return array<string, int>
     */
    public function dependentCounts(Project $project): array;

    /**
     * Fetch including soft-deleted rows — used by the restore endpoint.
     */
    public function findWithTrashedOrFail(int $id): Project;
}
