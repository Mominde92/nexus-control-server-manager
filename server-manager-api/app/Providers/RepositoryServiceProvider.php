<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\ActivityLogRepositoryInterface;
use App\Contracts\Repositories\ClientRepositoryInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\HostingRepositoryInterface;
use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Contracts\Repositories\ProviderRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\ActivityLogServiceInterface;
use App\Contracts\Services\ClientServiceInterface;
use App\Contracts\Services\DashboardServiceInterface;
use App\Contracts\Services\DomainServiceInterface;
use App\Contracts\Services\HostingServiceInterface;
use App\Contracts\Services\ProjectServiceInterface;
use App\Contracts\Services\ProviderServiceInterface;
use App\Contracts\Services\UserServiceInterface;
use App\Repositories\ActivityLogRepository;
use App\Repositories\ClientRepository;
use App\Repositories\DomainRepository;
use App\Repositories\HostingRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ProviderRepository;
use App\Repositories\UserRepository;
use App\Services\ActivityLogService;
use App\Services\ClientService;
use App\Services\DashboardService;
use App\Services\DomainService;
use App\Services\HostingService;
use App\Services\ProjectService;
use App\Services\ProviderService;
use App\Services\UserService;
use Illuminate\Support\ServiceProvider;

/**
 * Wires every module's abstraction to its concrete implementation.
 *
 * Controllers type-hint the *Interface*, never the class, so a repository can
 * be swapped (or faked in a test) without touching a single caller.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Interface => implementation.
     *
     * @var array<class-string, class-string>
     */
    private const array BINDINGS = [
        // Repositories (persistence)
        HostingRepositoryInterface::class => HostingRepository::class,
        DomainRepositoryInterface::class => DomainRepository::class,
        ProviderRepositoryInterface::class => ProviderRepository::class,
        ClientRepositoryInterface::class => ClientRepository::class,
        ProjectRepositoryInterface::class => ProjectRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        ActivityLogRepositoryInterface::class => ActivityLogRepository::class,

        // Services (business rules)
        HostingServiceInterface::class => HostingService::class,
        DomainServiceInterface::class => DomainService::class,
        ProviderServiceInterface::class => ProviderService::class,
        ClientServiceInterface::class => ClientService::class,
        ProjectServiceInterface::class => ProjectService::class,
        UserServiceInterface::class => UserService::class,
        ActivityLogServiceInterface::class => ActivityLogService::class,
        DashboardServiceInterface::class => DashboardService::class,
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
