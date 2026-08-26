<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\ServerAlertRepositoryInterface;
use App\Contracts\Repositories\ServerMetricRepositoryInterface;
use App\Contracts\Repositories\ServerRepositoryInterface;
use App\Contracts\Services\MonitoringServiceInterface;
use App\Contracts\Services\ServerServiceInterface;
use App\Repositories\ServerAlertRepository;
use App\Repositories\ServerMetricRepository;
use App\Repositories\ServerRepository;
use App\Services\MonitoringService;
use App\Services\ServerService;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the monitoring module's abstractions to their implementations.
 *
 * Kept separate from RepositoryServiceProvider so the module is self-contained:
 * monitoring can be added or removed without editing the bindings of any other
 * module.
 */
class MonitoringServiceProvider extends ServiceProvider
{
    /**
     * Interface => implementation.
     *
     * @var array<class-string, class-string>
     */
    private const array BINDINGS = [
        // Repositories (persistence)
        ServerRepositoryInterface::class => ServerRepository::class,
        ServerMetricRepositoryInterface::class => ServerMetricRepository::class,
        ServerAlertRepositoryInterface::class => ServerAlertRepository::class,

        // Services (business rules)
        ServerServiceInterface::class => ServerService::class,
        MonitoringServiceInterface::class => MonitoringService::class,
    ];

    public function register(): void
    {
        foreach (self::BINDINGS as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return array_keys(self::BINDINGS);
    }
}
