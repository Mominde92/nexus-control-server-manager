<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\ActivityLoggerInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Session and token issuance.
 *
 * The application authenticates two kinds of client from one credential check:
 *
 *  - The React SPA (`store`) receives an encrypted, httpOnly session cookie.
 *    No token is ever handed to JavaScript, so XSS cannot exfiltrate a
 *    long-lived credential.
 *  - Non-browser clients (`token`) receive a Sanctum personal access token,
 *    because they have no cookie jar and no CSRF story.
 *
 * `destroy` terminates whichever of the two the caller used.
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly ActivityLoggerInterface $logger,
    ) {}

    /**
     * SPA login. Establishes the stateful session cookie.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->credentials();

        // Resolve the account first so a blocked account gets a precise 403
        // rather than a misleading "wrong credentials" — but only *after* the
        // password is verified, so this branch cannot be used to probe which
        // addresses exist.
        $user = $this->lookup($credentials['email']);

        if ($user === null || ! Hash::check($credentials['password'], (string) $user->password)) {
            return $this->rejectCredentials($credentials['email']);
        }

        if (! $user->canAuthenticate()) {
            return $this->rejectInactive($user);
        }

        if (! Auth::guard('web')->attempt($credentials, $request->remember())) {
            // Defensive: the hash matched, so this only trips if a custom
            // provider rule rejected the user.
            return $this->rejectCredentials($credentials['email']);
        }

        // Session fixation defence: the ID the client held before authenticating
        // must not remain valid afterwards.
        $request->session()->regenerate();

        /** @var User $authenticated */
        $authenticated = Auth::guard('web')->user();

        $this->stampLogin($authenticated, $request);

        $this->logger->log(
            ActivityAction::Login,
            $authenticated,
            null,
            ['mode' => 'session', 'remember' => $request->remember()],
        );

        return ApiResponse::success(
            $this->payload($authenticated),
            'Signed in successfully.',
        );
    }

    /**
     * Token login for non-browser clients (CLI, mobile, CI).
     *
     * The plaintext token is returned exactly once — Sanctum only stores its
     * hash, so it cannot be shown again.
     */
    public function token(LoginRequest $request): JsonResponse
    {
        $credentials = $request->credentials();

        $user = $this->lookup($credentials['email']);

        if ($user === null || ! Hash::check($credentials['password'], (string) $user->password)) {
            return $this->rejectCredentials($credentials['email']);
        }

        if (! $user->canAuthenticate()) {
            return $this->rejectInactive($user);
        }

        $expiresAt = $this->tokenExpiry();

        $token = $user->createToken($request->deviceName(), ['*'], $expiresAt);

        $this->stampLogin($user, $request);

        $this->logger->logAs(
            ActivityAction::Login,
            (int) $user->getKey(),
            $user->name,
            $user,
            null,
            ['mode' => 'token', 'device_name' => $request->deviceName()],
        );

        return ApiResponse::created([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => $this->payload($user),
        ], 'Token issued successfully.');
    }

    /**
     * Log out of whichever credential the request arrived with.
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null) {
            $this->logger->log(ActivityAction::Logout, $user);
        }

        // Token clients: revoke just the presented token, leaving the user's
        // other devices signed in.
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();

            return ApiResponse::success(null, 'Signed out successfully.');
        }

        // Cookie clients: drop the session entirely and re-arm CSRF, so the
        // stale token in the old page cannot be replayed.
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::success(null, 'Signed out successfully.');
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * Soft-deleted accounts are excluded by the model's global scope, so a
     * deleted user simply fails the credential check — indistinguishable, from
     * the outside, from a wrong password.
     */
    private function lookup(string $email): ?User
    {
        return User::query()
            ->with(AuthUserResource::relations())
            ->where('email', $email)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        $user->loadMissing(AuthUserResource::relations());

        return AuthUserResource::make($user)->resolve();
    }

    private function stampLogin(User $user, Request $request): void
    {
        // Written without touching `updated_at` or firing model events: a login
        // is telemetry, not an edit to the user record.
        User::withTrashed()
            ->whereKey($user->getKey())
            ->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

        $user->setAttribute('last_login_at', now());
        $user->setAttribute('last_login_ip', $request->ip());
        $user->syncOriginal();
    }

    /**
     * Uniform rejection. The message never distinguishes "unknown email" from
     * "wrong password", and the failed attempt is attributed to no user.
     */
    private function rejectCredentials(string $email): JsonResponse
    {
        $this->logger->logAs(
            ActivityAction::LoginFailed,
            null,
            null,
            null,
            'Failed login attempt.',
            ['email' => $email],
        );

        return ApiResponse::error(
            'These credentials do not match our records.',
            422,
            ['email' => ['These credentials do not match our records.']],
        );
    }

    private function rejectInactive(User $user): JsonResponse
    {
        $status = $user->status?->label() ?? 'inactive';

        $this->logger->logAs(
            ActivityAction::LoginFailed,
            (int) $user->getKey(),
            $user->name,
            $user,
            'Login blocked: account is not active.',
            ['email' => $user->email, 'status' => $user->status?->value],
        );

        return ApiResponse::forbidden(
            sprintf('Your account is %s. Please contact an administrator.', mb_strtolower($status)),
        );
    }

    /**
     * Honour SANCTUM_TOKEN_EXPIRATION (minutes). Null means non-expiring.
     */
    private function tokenExpiry(): ?\DateTimeInterface
    {
        $minutes = Config::get('sanctum.expiration');

        return $minutes === null ? null : now()->addMinutes((int) $minutes);
    }
}
