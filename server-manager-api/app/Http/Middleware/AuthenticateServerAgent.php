<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Server;
use App\Models\ServerAgentCredential;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a monitoring agent from its bearer token.
 *
 * This is the gate on the hottest path in the system (every server, every
 * heartbeat interval), so the cost is deliberately fixed regardless of how many
 * servers exist:
 *
 *   1. Parse `sma_<tokenId>_<secret>` locally — no query for a malformed token.
 *   2. ONE lookup on the unique `token_id` index, joined to its server.
 *   3. ONE constant-time hash comparison of the secret half.
 *
 * It never scans the credential table, so adding servers does not slow
 * authentication down.
 *
 * A Server is authenticated here, not a User. Rather than making Server
 * `Authenticatable` (which would let a server flow into anything typed against
 * the user guard), the resolved server and credential are attached to the
 * request as attributes and read by the agent controllers. The `auth()` helper
 * stays exclusively about humans.
 */
class AuthenticateServerAgent
{
    /**
     * Request attribute keys the agent controllers read.
     */
    public const string SERVER_ATTRIBUTE = 'server';

    public const string CREDENTIAL_ATTRIBUTE = 'agent_credential';

    public function handle(Request $request, Closure $next): Response
    {
        $credential = $this->resolveCredential($request->bearerToken());

        if ($credential === null) {
            return ApiResponse::unauthorized('Invalid agent credential.');
        }

        /** @var Server $server */
        $server = $credential->getRelation('server');

        // Checked on every request, not just at issue time: switching monitoring
        // off (or soft-deleting the server) must stop ingest immediately, without
        // waiting for someone to remember to revoke the token.
        if (! $server->monitoring_enabled) {
            return ApiResponse::forbidden('Monitoring is disabled for this server.');
        }

        $credential->touchUsage($request->ip());

        $request->attributes->set(self::SERVER_ATTRIBUTE, $server);
        $request->attributes->set(self::CREDENTIAL_ATTRIBUTE, $credential);

        return $next($request);
    }

    /**
     * Resolve a usable credential (with its server eager loaded) from a
     * presented token, or null for anything that fails a check.
     *
     * Every failure mode returns the same null so the response cannot be used to
     * distinguish "no such token" from "revoked" from "expired".
     */
    private function resolveCredential(?string $token): ?ServerAgentCredential
    {
        if ($token === null || $token === '') {
            return null;
        }

        $parsed = ServerAgentCredential::parse($token);

        if ($parsed === null) {
            return null;
        }

        $credential = ServerAgentCredential::query()
            // Eager loaded: the middleware and both ingest controllers need the
            // server, and preventLazyLoading() is on outside production.
            ->with('server')
            ->where('token_id', $parsed['token_id'])
            ->first();

        if ($credential === null || ! $credential->matches($parsed['secret'])) {
            return null;
        }

        if (! $credential->isUsable()) {
            return null;
        }

        // A soft-deleted server keeps its rows (and therefore its credential),
        // but must not be able to ingest.
        return $credential->getRelation('server') instanceof Server ? $credential : null;
    }
}
