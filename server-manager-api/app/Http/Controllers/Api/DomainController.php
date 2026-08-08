<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ActivityLoggerInterface;
use App\Contracts\Services\DomainServiceInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\StoreDomainRequest;
use App\Http\Requests\Domain\UpdateDomainRequest;
use App\Http\Resources\DomainResource;
use App\Models\Domain;
use App\Support\ApiResponse;
use App\Support\QueryOptions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registered domains.
 */
class DomainController extends Controller
{
    use AuthorizesRequests;

    /**
     * @var array<int, string>
     */
    private const array FILTERS = [
        'status',
        'registrar_status',
        'domain_type',
        'provider_id',
        'project_id',
        'client_id',
        'hosting_id',
        'auto_renewal',
        'whois_protection',
        'expiring_within',
        'expired',
        'expiry_from',
        'expiry_to',
        'price_min',
        'price_max',
    ];

    /**
     * @var array<string, string>
     */
    private const array EXPORT_COLUMNS = [
        'ID' => 'id',
        'Domain' => 'name',
        'Provider' => 'provider',
        'Type' => 'domain_type',
        'Hosting' => 'hosting',
        'Username' => 'username',
        'Registrar URL' => 'registrar_url',
        'Registrar Username' => 'registrar_username',
        'Registrar Status' => 'registrar_status',
        'Plan' => 'plan',
        'Price' => 'price',
        'Currency' => 'currency',
        'Purchase Date' => 'purchase_date',
        'Expiry Date' => 'expiry_date',
        'Days Until Expiry' => 'days_until_expiry',
        'Status' => 'status',
        'Auto Renewal' => 'auto_renewal',
        'WHOIS Privacy' => 'whois_protection',
        'Project' => 'project',
        'Client' => 'client',
        'Created At' => 'created_at',
    ];

    public function __construct(
        private readonly DomainServiceInterface $domains,
        private readonly ActivityLoggerInterface $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Domain::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS);

        return ApiResponse::paginated(
            DomainResource::collection($this->domains->paginate($options)),
        );
    }

    public function store(StoreDomainRequest $request): JsonResponse
    {
        $this->authorize('create', Domain::class);

        $domain = $this->domains->create($request->validated());

        return ApiResponse::created(
            new DomainResource($this->domains->find((int) $domain->getKey())),
            'Domain created successfully.',
        );
    }

    public function show(Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);

        return ApiResponse::resource(
            new DomainResource($this->domains->find((int) $domain->getKey())),
        );
    }

    public function update(UpdateDomainRequest $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $updated = $this->domains->update($domain, $request->validated());

        return ApiResponse::resource(
            new DomainResource($this->domains->find((int) $updated->getKey())),
            'Domain updated successfully.',
        );
    }

    public function destroy(Domain $domain): JsonResponse
    {
        $this->authorize('delete', $domain);

        $this->domains->delete($domain);

        return ApiResponse::deleted('Domain deleted successfully.');
    }

    /**
     * Undo a soft delete; the id is resolved explicitly because route-model
     * binding never returns a trashed record.
     */
    public function restore(int $domain): JsonResponse
    {
        $record = $this->domains->findTrashed($domain);

        $this->authorize('restore', $record);

        $this->domains->restore($record);

        return ApiResponse::resource(
            new DomainResource($this->domains->find((int) $record->getKey())),
            'Domain restored successfully.',
        );
    }

    public function credentials(Domain $domain): JsonResponse
    {
        $this->authorize('viewCredentials', $domain);

        return ApiResponse::success(
            $this->domains->revealCredentials($domain),
            'Credentials revealed.',
        );
    }

    /**
     * Stream every matching row as CSV, credentials excluded.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Domain::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS)->withoutPagination();
        $records = $this->domains->all($options);

        $this->activity->log(
            ActivityAction::Exported,
            null,
            'Exported domain records.',
            ['format' => 'csv', 'count' => $records->count(), 'filters' => $options->filters],
        );

        $filename = 'domains-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, array_keys(self::EXPORT_COLUMNS));

            foreach ($records as $domain) {
                fputcsv($handle, $this->exportRow($domain));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function exportRow(Domain $domain): array
    {
        $values = [
            'id' => (int) $domain->getKey(),
            'name' => $domain->name,
            'provider' => $domain->providerLabel(),
            'domain_type' => $domain->domain_type?->label(),
            'hosting' => $domain->hosting?->title,
            'username' => $domain->username,
            'registrar_url' => $domain->registrar_url,
            'registrar_username' => $domain->registrar_username,
            'registrar_status' => $domain->registrar_status?->label(),
            'plan' => $domain->plan,
            'price' => $domain->price,
            'currency' => $domain->currency,
            'purchase_date' => $domain->purchase_date?->toDateString(),
            'expiry_date' => $domain->expiry_date?->toDateString(),
            'days_until_expiry' => $domain->daysUntilExpiry(),
            'status' => $domain->status?->label(),
            'auto_renewal' => $domain->auto_renewal ? 'Yes' : 'No',
            'whois_protection' => $domain->whois_protection ? 'Yes' : 'No',
            'project' => $domain->project?->name,
            'client' => $domain->client?->name,
            'created_at' => $domain->created_at?->toDateTimeString(),
        ];

        return array_map(
            static fn (string $key): mixed => $values[$key] ?? null,
            array_values(self::EXPORT_COLUMNS),
        );
    }
}
