<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ActivityLoggerInterface;
use App\Contracts\Services\ProjectServiceInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Api\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\ApiResponse;
use App\Support\QueryOptions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Project endpoints.
 */
class ProjectController extends Controller
{
    use AuthorizesRequests;
    use ExportsCsv;

    /**
     * @var array<int, string>
     */
    private const array FILTERS = ['status', 'client_id'];

    public function __construct(
        private readonly ProjectServiceInterface $projects,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS);

        return ApiResponse::paginated(
            ProjectResource::collection($this->projects->paginate($options))
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $project = $this->projects->create($request->validated());

        return ApiResponse::created(
            new ProjectResource($this->projects->find($project->getKey())),
            'Project created successfully.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $project = $this->projects->find($id);

        $this->authorize('view', $project);

        return ApiResponse::resource(new ProjectResource($project));
    }

    public function update(UpdateProjectRequest $request, int $id): JsonResponse
    {
        $project = $this->projects->find($id);

        $this->authorize('update', $project);

        $this->projects->update($project, $request->validated());

        // Re-read so the response carries the (possibly reassigned) client.
        return ApiResponse::resource(
            new ProjectResource($this->projects->find($id)),
            'Project updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $project = $this->projects->find($id);

        $this->authorize('delete', $project);

        $this->projects->delete($project);

        return ApiResponse::deleted('Project deleted successfully.');
    }

    public function restore(int $id): JsonResponse
    {
        $project = $this->projects->findWithTrashed($id);

        $this->authorize('restore', $project);

        $this->projects->restore($project);

        return ApiResponse::resource(
            new ProjectResource($this->projects->find($id)),
            'Project restored successfully.'
        );
    }

    public function export(Request $request, ActivityLoggerInterface $logger): StreamedResponse
    {
        $this->authorize('export', Project::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS)->withoutPagination();
        $projects = $this->projects->all($options);

        $logger->log(
            ActivityAction::Exported,
            null,
            'Exported projects',
            ['count' => $projects->count(), 'filters' => $options->filters],
        );

        return $this->streamCsv(
            'projects',
            ['ID', 'Name', 'Status', 'Client', 'Company', 'Start Date', 'End Date', 'Hostings', 'Domains', 'Description', 'Created At'],
            $projects,
            static fn (Project $project): array => [
                $project->id,
                $project->name,
                $project->status?->label(),
                $project->client?->name,
                $project->client?->company,
                $project->start_date?->format('Y-m-d'),
                $project->end_date?->format('Y-m-d'),
                $project->hostings_count,
                $project->domains_count,
                $project->description,
                $project->created_at?->format('Y-m-d H:i:s'),
            ],
        );
    }
}
