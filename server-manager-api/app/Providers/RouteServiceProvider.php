<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\AuthenticateServerAgent;
use App\Models\Server;
use App\Support\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Rate limiters protecting the API surface.
 *
 * Authentication endpoints are limited far more aggressively than reads,
 * because they are the ones worth brute-forcing.
 */
class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        $apiLimit = (int) config('app.rate_limits.api', 120);
        $authLimit = (int) config('app.rate_limits.auth', 5);

        // General authenticated API traffic: per user, falling back to IP.
        RateLimiter::for('api', function (Request $request) use ($apiLimit) {
            return Limit::perMinute($apiLimit)
                ->by($this->requestKey($request));
        });

        // Login / forgot-password / reset: keyed on BOTH the submitted email
        // and the IP, so an attacker cannot rotate one to evade the other.
        RateLimiter::for('auth', function (Request $request) use ($authLimit) {
            $email = (string) $request->input('email', '');

            return [
                Limit::perMinute($authLimit)
                    ->by('auth:email:'.mb_strtolower($email))
                    ->response($this->tooManyAttempts()),
                Limit::perMinute($authLimit * 4)
                    ->by('auth:ip:'.($request->ip() ?? 'unknown'))
                    ->response($this->tooManyAttempts()),
            ];
        });

        // Exports are expensive: keep them scarce per user.
        RateLimiter::for('export', function (Request $request) {
            return Limit::perMinute(10)
                ->by($this->requestKey($request));
        });

        // Credential reveals are security-sensitive and heavily audited.
        RateLimiter::for('credentials', function (Request $request) {
            return Limit::perMinute(20)
                ->by($this->requestKey($request));
        });

        // Agent ingest: keyed on the SERVER the credential resolved to, not on the
        // IP or the user. That distinction matters — many agents can share one
        // NAT address, so an IP-keyed bucket would let one busy site throttle its
        // neighbours, while a server-keyed bucket caps each host independently and
        // scales automatically as servers are added.
        RateLimiter::for('agent-ingest', function (Request $request) {
            $server = $request->attributes->get(AuthenticateServerAgent::SERVER_ATTRIBUTE);

            $key = $server instanceof Server
                ? 'server:'.$server->getKey()
                // Unauthenticated requests never reach ingest (the middleware
                // rejects them first); this is only a defensive fallback.
                : 'ip:'.($request->ip() ?? 'unknown');

            return Limit::perMinute((int) config('monitoring.agent.ingest_rate_limit', 120))
                ->by('agent-ingest:'.$key)
                ->response($this->tooManyAttempts());
        });
    }

    /**
     * Stable throttle key: the authenticated user when known, otherwise the
     * client IP. Never null, so the limiter cannot collapse all traffic into a
     * single shared bucket.
     */
    private function requestKey(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId !== null) {
            return 'user:'.$userId;
        }

        return 'ip:'.($request->ip() ?? 'unknown');
    }

    /**
     * Keep 429s inside the standard API envelope.
     */
    private function tooManyAttempts(): callable
    {
        return static fn (Request $request, array $headers) => ApiResponse::error(
            'Too many attempts. Please try again later.',
            429,
        )->withHeaders($headers);
    }
}
