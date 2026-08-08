<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * Authorisation wiring.
 *
 * Policies in `app/Policies` are auto-discovered by Laravel's naming
 * convention ({Model}Policy), so they are not listed manually here.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureSuperAdmin();
        $this->configureSanctum();
    }

    /**
     * Admins implicitly pass every ability check.
     *
     * Returning `null` (not `false`) for everyone else is important: it lets
     * the individual policy decide instead of short-circuiting to a denial.
     */
    private function configureSuperAdmin(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            unset($ability);

            return $user->primaryRole() === UserRole::Admin ? true : null;
        });
    }

    private function configureSanctum(): void
    {
        // Prune abilities we do not use, and keep the default token model.
        Sanctum::usePersonalAccessTokenModel(\Laravel\Sanctum\PersonalAccessToken::class);
    }
}
