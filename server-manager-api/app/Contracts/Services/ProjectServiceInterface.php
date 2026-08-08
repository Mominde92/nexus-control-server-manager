<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Project;

/**
 * Application service for the Project module.
 *
 * @extends CrudServiceInterface<Project>
 */
interface ProjectServiceInterface extends CrudServiceInterface
{
    /**
     * Fetch including soft-deleted rows so the restore endpoint can authorise
     * against a real model instance.
     */
    public function findWithTrashed(int $id): Project;

    /**
     * Project counts keyed by status value.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array;
}
