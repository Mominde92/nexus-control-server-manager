<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreTokenRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal access token management for the signed-in user.
 *
 * Every query is scoped through `$user->tokens()`, so one user can never see or
 * revoke another's token even by guessing an ID. Only the token's metadata is
 * ever readable: Sanctum stores a SHA-256 hash, so the secret genuinely cannot
 * be re-displayed after creation.
 */
class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tokens = $user->tokens()
            ->latest('id')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => $this->describe($token))
            ->all();

        return ApiResponse::success($tokens);
    }

    public function store(StoreTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $expiresAt = $this->expiry();

        $token = $user->createToken(
            (string) $request->validated('name'),
            $request->abilities(),
            $expiresAt,
        );

        /** @var PersonalAccessToken $model */
        $model = $token->accessToken;

        return ApiResponse::created([
            // Shown exactly once. There is no endpoint that can return it again.
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            ...$this->describe($model),
        ], 'Token created. Copy it now — it will not be shown again.');
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deleted = $user->tokens()->whereKey($token)->delete();

        if ($deleted === 0) {
            return ApiResponse::notFound('Token not found.');
        }

        return ApiResponse::deleted('Token revoked successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Honour SANCTUM_TOKEN_EXPIRATION (minutes); null means the token does not
     * expire on its own and must be revoked explicitly.
     */
    private function expiry(): ?\DateTimeInterface
    {
        $minutes = Config::get('sanctum.expiration');

        return $minutes === null ? null : now()->addMinutes((int) $minutes);
    }
}
