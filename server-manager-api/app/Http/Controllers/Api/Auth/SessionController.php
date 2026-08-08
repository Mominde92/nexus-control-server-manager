<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/auth/me` — the SPA's bootstrap call.
 *
 * Because the session lives in an httpOnly cookie the client cannot inspect,
 * this endpoint is how the app discovers who it is talking to and what that user
 * is allowed to do after a page reload.
 */
class SessionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Explicit eager load: AuthUserResource reads roles and permissions, and
        // lazy loading is disabled outside production.
        $user->loadMissing(AuthUserResource::relations());

        return ApiResponse::success(
            AuthUserResource::make($user)->resolve(),
        );
    }
}
