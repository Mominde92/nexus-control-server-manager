<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Services\DashboardServiceInterface;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ExpiringItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only dashboard endpoints.
 *
 * Each method is a separate request so the SPA can render the summary cards
 * immediately and stream the three lists in behind them, rather than blocking
 * the whole screen on the slowest query.
 */
class DashboardController extends Controller
{
    /**
     * Hard ceiling on list endpoints: the dashboard shows a preview, and the
     * module screens exist for the full set.
     */
    private const int MAX_LIMIT = 50;

    public function __construct(
        private readonly DashboardServiceInterface $dashboard,
    ) {}

    public function stats(): JsonResponse
    {
        Gate::authorize(Permission::ViewDashboard->value);

        return ApiResponse::success([
            ...$this->dashboard->statistics(),
            'expiry_window_days' => $this->dashboard->windowDays(),
        ]);
    }

    public function expiringHostings(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ViewDashboard->value);

        $items = $this->dashboard->expiringHostings(
            $this->days($request),
            $this->limit($request),
        );

        return ApiResponse::success(
            ExpiringItemResource::collection($items)->resolve(),
        );
    }

    public function expiringDomains(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ViewDashboard->value);

        $items = $this->dashboard->expiringDomains(
            $this->days($request),
            $this->limit($request),
        );

        return ApiResponse::success(
            ExpiringItemResource::collection($items)->resolve(),
        );
    }

    public function recentActivities(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ViewDashboard->value);

        $activities = $this->dashboard->recentActivities($this->limit($request, 15));

        return ApiResponse::success(
            ActivityLogResource::collection($activities)->resolve(),
        );
    }

    /**
     * Look-ahead window, defaulting to the configured one.
     */
    private function days(Request $request): int
    {
        $days = $request->integer('days', $this->dashboard->windowDays());

        return max(1, min($days, 365));
    }

    private function limit(Request $request, int $default = 10): int
    {
        $limit = $request->integer('limit', $default);

        return max(1, min($limit, self::MAX_LIMIT));
    }
}
