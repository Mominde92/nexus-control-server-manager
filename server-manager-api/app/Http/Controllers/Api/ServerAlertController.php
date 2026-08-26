<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\ServerAlertRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServerAlertResource;
use App\Models\Server;
use App\Models\ServerAlert;
use App\Support\ApiResponse;
use App\Support\QueryOptions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Alert triage.
 *
 * Alerts are cross-server and unbounded in number, so `index` is paginated and
 * filtered in SQL — nothing here loads the estate to sort it in PHP.
 *
 * Acknowledging and resolving are distinct on purpose: acknowledging says "a human
 * has seen this" without claiming the underlying condition is gone, whereas
 * resolving closes the incident. Threshold alerts normally resolve themselves when
 * the value recovers; the manual endpoint exists for conditions an operator has
 * dealt with out of band.
 */
class ServerAlertController extends Controller
{
    use AuthorizesRequests;

    /**
     * @var array<int, string>
     */
    private const array FILTERS = ['severity', 'type', 'server_id', 'status'];

    public function __construct(
        private readonly ServerAlertRepositoryInterface $alerts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Server::class);

        $options = QueryOptions::fromRequest($request, self::FILTERS);

        return ApiResponse::paginated(
            ServerAlertResource::collection($this->alerts->paginate($options)),
        );
    }

    public function show(ServerAlert $alert): JsonResponse
    {
        $this->authorize('viewAny', Server::class);

        return ApiResponse::resource(
            new ServerAlertResource($this->alerts->findOrFail((int) $alert->getKey())),
        );
    }

    /**
     * Mark an alert as seen, without asserting the condition has cleared.
     */
    public function acknowledge(ServerAlert $alert): JsonResponse
    {
        $this->authorize('manageAlerts', Server::class);

        if ($alert->isAcknowledged()) {
            return ApiResponse::resource(
                new ServerAlertResource($this->alerts->findOrFail((int) $alert->getKey())),
                'Alert was already acknowledged.',
            );
        }

        $alert->forceFill([
            'acknowledged_at' => now(),
            'acknowledged_by' => Auth::id(),
        ])->save();

        return ApiResponse::resource(
            new ServerAlertResource($this->alerts->findOrFail((int) $alert->getKey())),
            'Alert acknowledged.',
        );
    }

    /**
     * Close an incident manually.
     *
     * If the underlying condition is still breaching, the next evaluation will
     * legitimately open a fresh alert — which is the correct behaviour, not a bug:
     * the record shows it was closed and recurred.
     */
    public function resolve(ServerAlert $alert): JsonResponse
    {
        $this->authorize('manageAlerts', Server::class);

        if (! $alert->isOpen()) {
            return ApiResponse::resource(
                new ServerAlertResource($this->alerts->findOrFail((int) $alert->getKey())),
                'Alert was already resolved.',
            );
        }

        $alert->forceFill([
            'resolved_at' => now(),
            'last_observed_at' => $alert->last_observed_at ?? now(),
        ])->save();

        return ApiResponse::resource(
            new ServerAlertResource($this->alerts->findOrFail((int) $alert->getKey())),
            'Alert resolved.',
        );
    }
}
