<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Contracts\Services\ProjectServiceInterface;
use App\Models\Project;
use App\Services\Concerns\NormalisesInput;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Business rules for the Project module.
 *
 * @extends BaseCrudService<Project>
 */
class ProjectService extends BaseCrudService implements ProjectServiceInterface
{
    use NormalisesInput;

    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
    ) {
        parent::__construct($projects);
    }

    public function findWithTrashed(int $id): Project
    {
        return $this->projects->findWithTrashedOrFail($id);
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        return $this->projects->countByStatus();
    }

    /**
     * Hostings and domains are grouped by project on every screen; deleting the
     * project would leave them unattributed. Block and report.
     *
     * @param  Project  $model
     */
    public function delete(Model $model): bool
    {
        $counts = $this->projects->dependentCounts($model);
        $blockers = [];

        if ($counts['hostings'] > 0) {
            $blockers[] = $counts['hostings'].' hosting(s)';
        }

        if ($counts['domains'] > 0) {
            $blockers[] = $counts['domains'].' domain(s)';
        }

        if ($blockers !== []) {
            throw new RuntimeException(sprintf(
                'Cannot delete project "%s": %s still assigned to it. Reassign or remove them first.',
                $model->name,
                implode(' and ', $blockers),
            ));
        }

        return parent::delete($model);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        return $this->trimAndNullify($data);
    }
}
