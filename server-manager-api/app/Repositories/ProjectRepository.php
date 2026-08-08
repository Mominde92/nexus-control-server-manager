<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Project>
 */
class ProjectRepository extends BaseRepository implements ProjectRepositoryInterface
{
    /**
     * @return class-string<Project>
     */
    public function modelClass(): string
    {
        return Project::class;
    }

    /**
     * Column-limited: the list and detail screens only render the client's
     * name/company, so there is no reason to hydrate the full row.
     *
     * @return array<int, string>
     */
    protected function defaultRelations(): array
    {
        return ['client:id,name,company'];
    }

    /**
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return ['status', 'client_id'];
    }

    /**
     * @return Builder<Project>
     */
    public function query(): Builder
    {
        return parent::query()->withCount(['hostings', 'domains']);
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        /** @var array<string, int> */
        return Project::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function dependentCounts(Project $project): array
    {
        return [
            'hostings' => $project->hostings()->count(),
            'domains' => $project->domains()->count(),
        ];
    }

    public function findWithTrashedOrFail(int $id): Project
    {
        return $this->query()
            ->with($this->defaultRelations())
            ->withTrashed()
            ->findOrFail($id);
    }
}
