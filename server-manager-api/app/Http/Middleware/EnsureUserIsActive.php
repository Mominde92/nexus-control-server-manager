<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests from users who were deactivated or suspended after their
 * session/token was issued.
 *
 * Without this, revoking access would only take effect at next login.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->canAuthenticate()) {
            $status = $user->status instanceof UserStatus
                ? $user->status->label()
                : 'inactive';

            // Revoke the credential that got them here. In Sanctum's stateful
            // (cookie) mode currentAccessToken() is a TransientToken with no
            // delete(), so only real personal access tokens are revoked; the
            // session itself is invalidated by the logout endpoint.
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return ApiResponse::forbidden(
                sprintf('Your account is %s. Please contact an administrator.', strtolower($status)),
            );
        }

        return $next($request);
    }
}
