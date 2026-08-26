<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\ServerRepositoryInterface;
use App\Contracts\Services\MonitoringServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServerSummaryResource;
use App\Models\Server;
use App\Support\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estate-wide monitoring overview.
 *
 * Both endpoints are deliberately O(1) in the number of servers:
 *
 *   - `summary` is a single aggregate query (cached briefly), not a count per
 *     health state and certainly not a loop over servers; and
 *   - `attention` is ordered and limited in SQL, so it returns the worst handful
 *     rather than sorting the whole estate in PHP.
 *
 * This is what lets the dashboard behave identically with 6 servers or 60,000.
 */
class MonitoringDashboardController extends Controller
{
    use AuthorizesRequests;

    /**
     * Default and maximum size of the attention list. A dashboard panel showing
     * "what is broken" is only useful when it is short.
     */
    private const int DEFAULT_ATTENTION_LIMIT = 10;

    private const int MAX_ATTENTION_LIMIT = 50;

    public function __construct(
        private readonly MonitoringServiceInterface $monitoring,
        private readonly ServerRepositoryInterface $servers,
    ) {}

    public function summary(): JsonResponse
    {
        $this->authorize('viewAny', Server::class);

        return ApiResponse::success(
            $this->monitoring->dashboardSummary(),
            'Monitoring summary.',
        );
    }

    /**
     * Servers needing an operator's attention, worst first.
     */
    public function attention(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Server::class);

        $limit = (int) $request->integer('limit', self::DEFAULT_ATTENTION_LIMIT);
        $limit = max(1, min($limit, self::MAX_ATTENTION_LIMIT));

        return ApiResponse::success(
            ServerSummaryResource::collection($this->servers->needingAttention($limit)),
            'Servers needing attention.',
        );
    }
}
