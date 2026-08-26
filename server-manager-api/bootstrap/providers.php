<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\MonitoringServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\RouteServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    RepositoryServiceProvider::class,
    MonitoringServiceProvider::class,
    RouteServiceProvider::class,
];
