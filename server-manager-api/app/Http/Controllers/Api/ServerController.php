<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ActivityLoggerInterface;
use App\Contracts\Services\ServerServiceInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\StoreServerRequest;
use App\Http\Requests\Server\UpdateServerRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Support\ApiResponse;
use App\Support\QueryOptions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitored servers.
 *
 * Thin by design: validation lives in the form requests, authorisation in
 * ServerPolicy, business rules in ServerService, and the envelope in ApiResponse.
 *
 * Every list read is paginated. That is not politeness — the number of servers is
 * unbounded, so an unpaginated `index` would be a denial-of-service waiting for
 * the estate to grow.
 */
class ServerController extends Controller
{
    use AuthorizesRequests;

    /**
     * Filter keys accepted by index/export. Anything else in the query string is
     * ignored rather than reaching the data layer.
     *
     * @var array<int, string>
     */
    private const array FILTERS = [
        'os',
        'monitoring_enabled',
        'hosting_id',
        'client_id',
        'project_id',
        'health',
    ];

    /**
     * CSV column header => resolver key.
     *
     * @var array<string, string>
     */
    private const array EXPORT_COLUMNS = [
        'ID' => 'id',
        'Name' => 'name',
        'Hostname' => 'hostname',
        'IP Address' => 'ip_address',
        'OS' => 'os',
        'OS Version' => 'os_version',
        'Monitoring' => 'monitoring_enabled',
        'Health' => 'health',
        'Last Heartbeat' => 'last_heartbeat_at',
        'Agent Version' => 'agent_version',
        'Open Alerts' => 'open_alert_count',
        'Hosting' => 'hosting',
        'Client' => 'client',
        'Project' => 'project',
        'Created At' => 'created_at',
    ];

    public function __construct(
        private readonly ServerServiceInterface $servers,
        private readonly ActivityLoggerInterface $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Server::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS);

        return ApiResponse::paginated(
            ServerResource::collection($this->servers->paginate($options)),
        );
    }

    public function store(StoreServerRequest $request): JsonResponse
    {
        $this->authorize('create', Server::class);

        $server = $this->servers->create($request->validated());

        // Re-read through the service so the response carries the same eager
        // loads (and therefore the same shape) as index/show.
        return ApiResponse::created(
            new ServerResource($this->servers->find((int) $server->getKey())),
            'Server created successfully.',
        );
    }

    public function show(Server $server): JsonResponse
    {
        $this->authorize('view', $server);

        return ApiResponse::resource(
            new ServerResource($this->servers->find((int) $server->getKey())),
        );
    }

    public function update(UpdateServerRequest $request, Server $server): JsonResponse
    {
        $this->authorize('update', $server);

        $updated = $this->servers->update($server, $request->validated());

        return ApiResponse::resource(
            new ServerResource($this->servers->find((int) $updated->getKey())),
            'Server updated successfully.',
        );
    }

    public function destroy(Server $server): JsonResponse
    {
        $this->authorize('delete', $server);

        $this->servers->delete($server);

        return ApiResponse::deleted('Server deleted successfully.');
    }

    /**
     * Undo a soft delete. The id is resolved explicitly because route-model
     * binding never returns a trashed record.
     */
    public function restore(int $server): JsonResponse
    {
        $record = $this->servers->findTrashed($server);

        $this->authorize('restore', $record);

        $this->servers->restore($record);

        return ApiResponse::resource(
            new ServerResource($this->servers->find((int) $record->getKey())),
            'Server restored successfully.',
        );
    }

    /**
     * Switch monitoring on. Health leaves "Disabled" immediately and becomes
     * "Awaiting first heartbeat" until an agent reports.
     */
    public function enableMonitoring(Server $server): JsonResponse
    {
        $this->authorize('update', $server);

        $updated = $this->servers->enableMonitoring($server);

        return ApiResponse::resource(
            new ServerResource($this->servers->find((int) $updated->getKey())),
            'Monitoring enabled.',
        );
    }

    /**
     * Switch monitoring off. Open alerts are resolved by the service, and the
     * agent middleware starts rejecting ingest for this server at once.
     */
    public function disableMonitoring(Server $server): JsonResponse
    {
        $this->authorize('update', $server);

        $updated = $this->servers->disableMonitoring($server);

        return ApiResponse::resource(
            new ServerResource($this->servers->find((int) $updated->getKey())),
            'Monitoring disabled.',
        );
    }

    /**
     * Stream every matching row as CSV.
     *
     * Streamed rather than buffered so a large estate never has to fit in memory,
     * and chunked by the repository's unpaginated read.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Server::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS)->withoutPagination();
        $records = $this->servers->all($options);

        $this->activity->log(
            ActivityAction::Exported,
            null,
            'Exported server records.',
            ['format' => 'csv', 'count' => $records->count(), 'filters' => $options->filters],
        );

        $filename = 'servers-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, array_keys(self::EXPORT_COLUMNS));

            foreach ($records as $server) {
                fputcsv($handle, $this->exportRow($server));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function exportRow(Server $server): array
    {
        $severity = $server->getAttribute('open_alert_severity');

        $values = [
            'id' => (int) $server->getKey(),
            'name' => $server->name,
            'hostname' => $server->hostname,
            'ip_address' => $server->ip_address,
            'os' => $server->os->label(),
            'os_version' => $server->os_version,
            'monitoring_enabled' => $server->monitoring_enabled ? 'yes' : 'no',
            'health' => $server->health(is_string($severity) ? $severity : null)->label(),
            'last_heartbeat_at' => $server->last_heartbeat_at?->toDateTimeString(),
            'agent_version' => $server->agent_version,
            'open_alert_count' => (int) ($server->getAttribute('open_alert_count') ?? 0),
            'hosting' => $server->hosting?->title,
            'client' => $server->client?->name,
            'project' => $server->project?->name,
            'created_at' => $server->created_at?->toDateTimeString(),
        ];

        return array_map(
            static fn (string $key): mixed => $values[$key] ?? null,
            array_values(self::EXPORT_COLUMNS),
        );
    }
}
