<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityLogController;
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
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RecordAccessController;
use App\Http\Controllers\Api\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:auth');
    Route::post('token', [LoginController::class, 'token'])->middleware('throttle:auth');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:6,1');
    Route::post('reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:6,1');
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
});
