<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ActivityLoggerInterface;
use App\Contracts\Services\ActivityLogServiceInterface;
use App\Enums\ActivityAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Support\ApiResponse;
use App\Support\QueryOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The audit trail, read-only.
 *
 * There is deliberately no store/update/destroy: entries are authored by
 * ActivityLoggerInterface and removed only by the `activity:prune` retention
 * command.
 */
class ActivityLogController extends Controller
{
    /**
     * Filter keys this endpoint accepts. Anything else in the query string is
     * ignored rather than reaching the query builder.
     *
     * @var array<int, string>
     */
    private const array FILTERS = [
        'action',
        'user_id',
        'subject_type',
        'subject_id',
        'date_from',
        'date_to',
    ];

    public function __construct(
        private readonly ActivityLogServiceInterface $logs,
        private readonly ActivityLoggerInterface $logger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ViewActivityLog->value);

        $options = QueryOptions::fromRequest($request, self::FILTERS);

        $page = $this->logs->paginate($options);

        return ApiResponse::paginated(
            ActivityLogResource::collection($page),
        );
    }

    public function show(ActivityLog $activityLog): JsonResponse
    {
        Gate::authorize(Permission::ViewActivityLog->value);

        // Route-model binding skips the repository's default eager loads.
        $activityLog->loadMissing('user:id,name');

        return ApiResponse::success(
            ActivityLogResource::make($activityLog)->resolve(),
        );
    }

    /**
     * Stream the filtered trail as CSV.
     *
     * Streamed rather than buffered: an audit export can be very large, and
     * building the whole file in memory before sending it is the one way to turn
     * a reporting feature into an outage. Rows are chunked out of the database
     * and written straight to the output handle.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize(Permission::ExportActivityLog->value);

        $options = QueryOptions::fromRequest($request, self::FILTERS)->withoutPagination();

        $this->logger->log(
            ActivityAction::Exported,
            null,
            'Exported the activity log.',
            ['filters' => $options->filters, 'search' => $options->search],
        );

        $filename = 'activity-log-'.now()->format('Y-m-d_His').'.csv';

        $rows = $this->logs->all($options);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // BOM so Excel opens UTF-8 correctly instead of mangling accents.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID', 'Date', 'Time', 'User', 'Action', 'Subject Type',
                'Subject ID', 'Subject', 'Summary', 'IP Address',
            ]);

            foreach ($rows as $log) {
                $action = $log->action instanceof ActivityAction ? $log->action : null;

                fputcsv($handle, [
                    $log->id,
                    $log->created_at?->format('d-m-Y'),
                    $log->created_at?->format('h:i A'),
                    $log->user_name ?? '',
                    $action?->label() ?? (string) $log->action,
                    $log->subject_type === null ? '' : class_basename($log->subject_type),
                    $log->subject_id ?? '',
                    $log->subject_label ?? '',
                    $log->summary(),
                    $log->ip_address ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
