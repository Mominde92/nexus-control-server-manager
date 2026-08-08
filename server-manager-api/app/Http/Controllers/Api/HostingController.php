<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ActivityLoggerInterface;
use App\Contracts\Services\HostingServiceInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hosting\StoreHostingRequest;
use App\Http\Requests\Hosting\UpdateHostingRequest;
use App\Http\Resources\HostingResource;
use App\Models\Hosting;
use App\Support\ApiResponse;
use App\Support\QueryOptions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hosting accounts.
 *
 * Thin by design: request validation lives in the form requests, authorisation
 * in HostingPolicy, business rules in HostingService, and the response envelope
 * in ApiResponse. This class only wires them together.
 */
class HostingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Filter keys accepted by index/export. Anything else in the query string
     * is ignored rather than passed to the data layer.
     *
     * @var array<int, string>
     */
    private const array FILTERS = [
        'status',
        'server_type',
        'provider_id',
        'project_id',
        'client_id',
        'ssl_certificate',
        'expiring_within',
        'expired',
        'expiry_from',
        'expiry_to',
        'price_min',
        'price_max',
    ];

    /**
     * CSV column header => attribute resolver.
     *
     * @var array<string, string>
     */
    private const array EXPORT_COLUMNS = [
        'ID' => 'id',
        'Title' => 'title',
        'Provider' => 'provider',
        'Server Type' => 'server_type',
        'Server Location' => 'server_location',
        'IP Address' => 'ip_address',
        'Control Panel' => 'control_panel_url',
        'Username' => 'username',
        'Plan' => 'plan',
        'Price' => 'price',
        'Currency' => 'currency',
        'Purchase Date' => 'purchase_date',
        'Expiry Date' => 'expiry_date',
        'Days Until Expiry' => 'days_until_expiry',
        'Status' => 'status',
        'SSL Certificate' => 'ssl_certificate',
        'Project' => 'project',
        'Client' => 'client',
        'Created At' => 'created_at',
    ];

    public function __construct(
        private readonly HostingServiceInterface $hostings,
        private readonly ActivityLoggerInterface $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Hosting::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS);

        return ApiResponse::paginated(
            HostingResource::collection($this->hostings->paginate($options)),
        );
    }

    public function store(StoreHostingRequest $request): JsonResponse
    {
        $this->authorize('create', Hosting::class);

        $hosting = $this->hostings->create($request->validated());

        // Re-read through the service so the response carries the same eager
        // loads (and therefore the same shape) as index/show.
        return ApiResponse::created(
            new HostingResource($this->hostings->find((int) $hosting->getKey())),
            'Hosting created successfully.',
        );
    }

    public function show(Hosting $hosting): JsonResponse
    {
        $this->authorize('view', $hosting);

        return ApiResponse::resource(
            new HostingResource($this->hostings->find((int) $hosting->getKey())),
        );
    }

    public function update(UpdateHostingRequest $request, Hosting $hosting): JsonResponse
    {
        $this->authorize('update', $hosting);

        $updated = $this->hostings->update($hosting, $request->validated());

        return ApiResponse::resource(
            new HostingResource($this->hostings->find((int) $updated->getKey())),
            'Hosting updated successfully.',
        );
    }

    public function destroy(Hosting $hosting): JsonResponse
    {
        $this->authorize('delete', $hosting);

        $this->hostings->delete($hosting);

        return ApiResponse::deleted('Hosting deleted successfully.');
    }

    /**
     * Undo a soft delete. The id is resolved explicitly because route-model
     * binding never returns a trashed record.
     */
    public function restore(int $hosting): JsonResponse
    {
        $record = $this->hostings->findTrashed($hosting);

        $this->authorize('restore', $record);

        $this->hostings->restore($record);

        return ApiResponse::resource(
            new HostingResource($this->hostings->find((int) $record->getKey())),
            'Hosting restored successfully.',
        );
    }

    /**
     * Decrypted vendor credentials for one record.
     *
     * Guarded by its own ability, and audited by the service — reading a secret
     * is an event in its own right.
     */
    public function credentials(Hosting $hosting): JsonResponse
    {
        $this->authorize('viewCredentials', $hosting);

        return ApiResponse::success(
            $this->hostings->revealCredentials($hosting),
            'Credentials revealed.',
        );
    }

    /**
     * Stream every matching row as CSV.
     *
     * Streamed rather than buffered so a large estate does not have to fit in
     * memory, and deliberately free of credential columns.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Hosting::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS)->withoutPagination();
        $records = $this->hostings->all($options);

        $this->activity->log(
            ActivityAction::Exported,
            null,
            'Exported hosting records.',
            ['format' => 'csv', 'count' => $records->count(), 'filters' => $options->filters],
        );

        $filename = 'hostings-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, array_keys(self::EXPORT_COLUMNS));

            foreach ($records as $hosting) {
                fputcsv($handle, $this->exportRow($hosting));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function exportRow(Hosting $hosting): array
    {
        $values = [
            'id' => (int) $hosting->getKey(),
            'title' => $hosting->title,
            'provider' => $hosting->providerLabel(),
            'server_type' => $hosting->server_type?->label(),
            'server_location' => $hosting->server_location,
            'ip_address' => $hosting->ip_address,
            'control_panel_url' => $hosting->control_panel_url,
            'username' => $hosting->username,
            'plan' => $hosting->plan,
            'price' => $hosting->price,
            'currency' => $hosting->currency,
            'purchase_date' => $hosting->purchase_date?->toDateString(),
            'expiry_date' => $hosting->expiry_date?->toDateString(),
            'days_until_expiry' => $hosting->daysUntilExpiry(),
            'status' => $hosting->status?->label(),
            'ssl_certificate' => $hosting->ssl_certificate?->label(),
            'project' => $hosting->project?->name,
            'client' => $hosting->client?->name,
            'created_at' => $hosting->created_at?->toDateTimeString(),
        ];

        return array_map(
            static fn (string $key): mixed => $values[$key] ?? null,
            array_values(self::EXPORT_COLUMNS),
        );
    }
}
