<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ActivityLoggerInterface;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Hosting;
use App\Models\Project;
use App\Models\Provider as HostingProvider;
use App\Models\Server;
use App\Models\ServerAlert;
use App\Models\User;
use App\Observers\FlushesDashboardCache;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Audit trail writer — singleton so withoutLogging() suppression
        // applies process-wide for the duration of a seeder or import.
        $this->app->singleton(ActivityLoggerInterface::class, ActivityLogger::class);
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureMorphMap();
        $this->configureObservers();
        $this->configurePasswordRules();
        $this->configureUrls();
    }

    /**
     * Keep the cached dashboard counters honest: any write to a Hosting or
     * Domain busts the statistics cache, so a newly created record shows up in
     * the totals immediately instead of after the 60s TTL.
     */
    private function configureObservers(): void
    {
        Hosting::observe(FlushesDashboardCache::class);
        Domain::observe(FlushesDashboardCache::class);
    }

    /**
     * Fail loudly in development instead of silently returning null for a
     * relation that was never eager loaded.
     */
    private function configureModels(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    /**
     * Stable polymorphic aliases so `activity_logs.subject_type` stores
     * "hosting" rather than a PHP class path — decoupling the audit trail from
     * future namespace refactors.
     */
    private function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'client' => Client::class,
            'provider' => HostingProvider::class,
            'project' => Project::class,
            'hosting' => Hosting::class,
            'domain' => Domain::class,
            'server' => Server::class,
            'server_alert' => ServerAlert::class,
        ]);
    }

    /**
     * Baseline password policy applied everywhere `Password::defaults()` is used
     * (registration, admin-created users, reset). Relaxed in tests/local so
     * factories stay simple.
     */
    private function configurePasswordRules(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(10);

            return $this->app->isProduction()
                ? $rule->mixedCase()->numbers()->symbols()->uncompromised()
                : $rule->mixedCase()->numbers();
        });
    }

    private function configureUrls(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
