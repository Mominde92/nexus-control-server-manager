<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ActivityLoggerInterface;
use App\Contracts\Services\ProviderServiceInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Api\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\StoreProviderRequest;
use App\Http\Requests\Provider\UpdateProviderRequest;
use App\Http\Resources\ProviderResource;
use App\Models\Provider;
use App\Support\ApiResponse;
use App\Support\QueryOptions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provider (vendor) endpoints.
 *
 * The controller stays thin on purpose: authorise, translate HTTP into a
 * QueryOptions / validated array, delegate to the service, wrap the result in
 * the shared envelope.
 */
class ProviderController extends Controller
{
    use AuthorizesRequests;
    use ExportsCsv;

    /**
     * Filters this endpoint accepts; anything else in the query string is
     * ignored rather than reaching the database.
     *
     * @var array<int, string>
     */
    private const array FILTERS = ['type', 'status'];

    public function __construct(
        private readonly ProviderServiceInterface $providers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Provider::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS);

        return ApiResponse::paginated(
            ProviderResource::collection($this->providers->paginate($options))
        );
    }

    public function store(StoreProviderRequest $request): JsonResponse
    {
        $this->authorize('create', Provider::class);

        $provider = $this->providers->create($request->validated());

        return ApiResponse::created(
            new ProviderResource($provider),
            'Provider created successfully.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $provider = $this->providers->find($id);

        $this->authorize('view', $provider);

        return ApiResponse::resource(new ProviderResource($provider));
    }

    public function update(UpdateProviderRequest $request, int $id): JsonResponse
    {
        $provider = $this->providers->find($id);

        $this->authorize('update', $provider);

        $updated = $this->providers->update($provider, $request->validated());

        return ApiResponse::resource(
            new ProviderResource($updated),
            'Provider updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $provider = $this->providers->find($id);

        $this->authorize('delete', $provider);

        $this->providers->delete($provider);

        return ApiResponse::deleted('Provider deleted successfully.');
    }

    public function restore(int $id): JsonResponse
    {
        $provider = $this->providers->findWithTrashed($id);

        $this->authorize('restore', $provider);

        $this->providers->restore($provider);

        return ApiResponse::resource(
            new ProviderResource($this->providers->find($id)),
            'Provider restored successfully.'
        );
    }

    /**
     * Stream every matching provider as CSV, ignoring pagination.
     */
    public function export(Request $request, ActivityLoggerInterface $logger): StreamedResponse
    {
        $this->authorize('export', Provider::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS)->withoutPagination();
        $providers = $this->providers->all($options);

        $logger->log(
            ActivityAction::Exported,
            null,
            'Exported providers',
            ['count' => $providers->count(), 'filters' => $options->filters],
        );

        return $this->streamCsv(
            'providers',
            ['ID', 'Name', 'Type', 'Status', 'Website', 'Support Email', 'Support Phone', 'Hostings', 'Domains', 'Notes', 'Created At'],
            $providers,
            static fn (Provider $provider): array => [
                $provider->id,
                $provider->name,
                $provider->type?->label(),
                $provider->status?->label(),
                $provider->url,
                $provider->support_email,
                $provider->support_phone,
                $provider->hostings_count,
                $provider->domains_count,
                $provider->notes,
                $provider->created_at?->format('Y-m-d H:i:s'),
            ],
        );
    }
}
