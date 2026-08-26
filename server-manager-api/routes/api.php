<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\Agent\HeartbeatController;
use App\Http\Controllers\Api\Agent\MetricController as AgentMetricController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\Auth\TokenController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\HostingController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\MonitoringDashboardController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\RecordAccessController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServerAgentController;
use App\Http\Controllers\Api\ServerAlertController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\ServerMetricController;
use App\Http\Controllers\Api\UserController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
 * Public liveness probe for load balancers and container health checks.
 * Deliberately reports no version or dependency detail — that would be free
 * reconnaissance for an unauthenticated caller.
 */
Route::get('health', fn () => ApiResponse::success(['status' => 'ok'], 'Service is healthy.'))
    ->name('api.health');

Route::prefix('auth')->group(function (): void {
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:auth');
    Route::post('token', [LoginController::class, 'token'])->middleware('throttle:auth');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:6,1');
    Route::post('reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:6,1');
});

/*
 * Monitoring agent ingest.
 *
 * Deliberately OUTSIDE the sanctum group: an agent is not a user and holds no
 * session. It authenticates with its own per-server bearer credential, which the
 * `agent` middleware resolves to exactly one Server. Note there is no `{server}`
 * parameter anywhere below — the target is derived from the token, so an agent can
 * only ever write to its own row and cannot enumerate or address another.
 *
 * The throttle is keyed on that resolved server id, so each host gets its own
 * budget however many agents share a public IP.
 */
Route::prefix('agent')->middleware(['agent', 'throttle:agent-ingest'])->group(function (): void {
    Route::post('heartbeat', [HeartbeatController::class, 'store']);
    Route::post('metrics', [AgentMetricController::class, 'store']);
    // Lets an agent fetch its interval and metric list at runtime, so behaviour is
    // server-driven rather than baked into the agent build.
    Route::get('config', [HeartbeatController::class, 'config']);
});

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::get('me', [SessionController::class, 'show']);
        Route::post('logout', [LoginController::class, 'destroy']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('password', [PasswordController::class, 'update']);
        Route::apiResource('tokens', TokenController::class)->only(['index', 'store', 'destroy']);
    });

    Route::prefix('dashboard')->group(function (): void {
        Route::get('stats', [DashboardController::class, 'stats']);
        Route::get('expiring-hostings', [DashboardController::class, 'expiringHostings']);
        Route::get('expiring-domains', [DashboardController::class, 'expiringDomains']);
        Route::get('recent-activities', [DashboardController::class, 'recentActivities']);
    });

    Route::get('meta/options', [MetaController::class, 'options']);
    Route::apiResource('roles', RoleController::class);
    Route::get('{resource}/{id}/access', [RecordAccessController::class, 'index'])->whereIn('resource', ['hostings', 'domains'])->whereNumber('id');
    Route::put('{resource}/{id}/access', [RecordAccessController::class, 'update'])->whereIn('resource', ['hostings', 'domains'])->whereNumber('id');
    Route::patch('users/{id}/restore', [UserController::class, 'restore'])->whereNumber('id');
    Route::patch('users/{id}/status', [UserController::class, 'status'])->whereNumber('id');
    Route::apiResource('users', UserController::class)->parameters(['users' => 'id']);

    foreach ([
        'clients' => ClientController::class,
        'providers' => ProviderController::class,
        'projects' => ProjectController::class,
    ] as $resource => $controller) {
        Route::get("{$resource}/export", [$controller, 'export']);
        Route::patch("{$resource}/{id}/restore", [$controller, 'restore'])->whereNumber('id');
        Route::apiResource($resource, $controller)->parameters([$resource => 'id']);
    }

    Route::get('hostings/export', [HostingController::class, 'export']);
    Route::patch('hostings/{hosting}/restore', [HostingController::class, 'restore'])->whereNumber('hosting');
    Route::apiResource('hostings', HostingController::class);
    Route::get('domains/export', [DomainController::class, 'export']);
    Route::patch('domains/{domain}/restore', [DomainController::class, 'restore'])->whereNumber('domain');
    Route::apiResource('domains', DomainController::class);
    Route::get('hostings/{hosting}/credentials', [HostingController::class, 'credentials']);
    Route::get('domains/{domain}/credentials', [DomainController::class, 'credentials']);
    Route::get('activity-logs/export', [ActivityLogController::class, 'export']);
    Route::apiResource('activity-logs', ActivityLogController::class)->only(['index', 'show']);

    /*
     * Server monitoring.
     */
    Route::get('monitoring/summary', [MonitoringDashboardController::class, 'summary']);
    Route::get('monitoring/attention', [MonitoringDashboardController::class, 'attention']);

    // Both MUST precede the apiResource: otherwise `/servers/export` is swallowed
    // by `/servers/{server}` and answers 404 for a non-numeric id.
    Route::get('servers/export', [ServerController::class, 'export'])->middleware('throttle:export');
    Route::patch('servers/{server}/restore', [ServerController::class, 'restore'])->whereNumber('server');

    Route::apiResource('servers', ServerController::class);

    Route::post('servers/{server}/monitoring/enable', [ServerController::class, 'enableMonitoring']);
    Route::post('servers/{server}/monitoring/disable', [ServerController::class, 'disableMonitoring']);

    // Credential management mints bearer secrets, so it carries the same
    // aggressive throttle as the credential-reveal endpoints.
    Route::middleware('throttle:credentials')->group(function (): void {
        Route::get('servers/{server}/agent-credentials', [ServerAgentController::class, 'index']);
        Route::post('servers/{server}/agent-credentials', [ServerAgentController::class, 'store']);
        Route::post('servers/{server}/agent-credentials/rotate', [ServerAgentController::class, 'rotate']);
        Route::delete('servers/{server}/agent-credentials/{credential}', [ServerAgentController::class, 'destroy']);
        Route::get('servers/{server}/agent-instructions', [ServerAgentController::class, 'instructions']);
    });

    Route::get('servers/{server}/metrics/latest', [ServerMetricController::class, 'latest']);
    Route::get('servers/{server}/metrics', [ServerMetricController::class, 'series']);

    Route::get('server-alerts', [ServerAlertController::class, 'index']);
    Route::get('server-alerts/{alert}', [ServerAlertController::class, 'show'])->whereNumber('alert');
    Route::post('server-alerts/{alert}/acknowledge', [ServerAlertController::class, 'acknowledge'])->whereNumber('alert');
    Route::post('server-alerts/{alert}/resolve', [ServerAlertController::class, 'resolve'])->whereNumber('alert');
});
