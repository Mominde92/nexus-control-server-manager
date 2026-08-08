<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ActivityLoggerInterface;
use App\Contracts\Services\ClientServiceInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Api\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Support\ApiResponse;
use App\Support\QueryOptions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Client endpoints. Clients are filtered by free-text search only — there is no
 * status column to narrow by.
 */
class ClientController extends Controller
{
    use AuthorizesRequests;
    use ExportsCsv;

    /**
     * @var array<int, string>
     */
    private const array FILTERS = [];

    public function __construct(
        private readonly ClientServiceInterface $clients,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS);

        return ApiResponse::paginated(
            ClientResource::collection($this->clients->paginate($options))
        );
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $this->authorize('create', Client::class);

        $client = $this->clients->create($request->validated());

        return ApiResponse::created(
            new ClientResource($client),
            'Client created successfully.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $client = $this->clients->find($id);

        $this->authorize('view', $client);

        return ApiResponse::resource(new ClientResource($client));
    }

    public function update(UpdateClientRequest $request, int $id): JsonResponse
    {
        $client = $this->clients->find($id);

        $this->authorize('update', $client);

        $updated = $this->clients->update($client, $request->validated());

        return ApiResponse::resource(
            new ClientResource($updated),
            'Client updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $client = $this->clients->find($id);

        $this->authorize('delete', $client);

        $this->clients->delete($client);

        return ApiResponse::deleted('Client deleted successfully.');
    }

    public function restore(int $id): JsonResponse
    {
        $client = $this->clients->findWithTrashed($id);

        $this->authorize('restore', $client);

        $this->clients->restore($client);

        return ApiResponse::resource(
            new ClientResource($this->clients->find($id)),
            'Client restored successfully.'
        );
    }

    public function export(Request $request, ActivityLoggerInterface $logger): StreamedResponse
    {
        $this->authorize('export', Client::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS)->withoutPagination();
        $clients = $this->clients->all($options);

        $logger->log(
            ActivityAction::Exported,
            null,
            'Exported clients',
            ['count' => $clients->count(), 'filters' => $options->filters],
        );

        return $this->streamCsv(
            'clients',
            ['ID', 'Name', 'Company', 'Email', 'Phone', 'Website', 'Address', 'Projects', 'Hostings', 'Domains', 'Notes', 'Created At'],
            $clients,
            static fn (Client $client): array => [
                $client->id,
                $client->name,
                $client->company,
                $client->email,
                $client->phone,
                $client->website,
                $client->address,
                $client->projects_count,
                $client->hostings_count,
                $client->domains_count,
                $client->notes,
                $client->created_at?->format('Y-m-d H:i:s'),
            ],
        );
    }
}
