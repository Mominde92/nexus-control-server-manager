<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\ActivityLoggerInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Completes a password reset from the emailed token.
 *
 * A successful reset is treated as a compromise-recovery event: every existing
 * credential for that account is destroyed. Otherwise an attacker who had
 * already stolen a session or an API token would keep their access even after
 * the owner "locked them out" by changing the password.
 */
class ResetPasswordController extends Controller
{
    public function __construct(
        private readonly ActivityLoggerInterface $logger,
    ) {}

    public function store(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $status = Password::broker()->reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    // Invalidate "remember me" cookies issued before the reset.
                    'remember_token' => Str::random(60),
                ])->save();

                // Kill every personal access token.
                $user->tokens()->delete();

                Event::dispatch(new PasswordResetEvent($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            return $this->rejectToken($status);
        }

        $user = User::query()->where('email', $data['email'])->first();

        if ($user !== null) {
            // Invalidate cookie sessions everywhere. The session table is the
            // authoritative store, so deleting the user's rows signs out every
            // browser immediately rather than at next rotation.
            $this->purgeSessions((int) $user->getKey());

            $this->logger->logAs(
                ActivityAction::PasswordReset,
                (int) $user->getKey(),
                $user->name,
                $user,
                'Password reset completed; all sessions and tokens revoked.',
            );
        }

        return ApiResponse::success(
            null,
            'Your password has been reset. Please sign in with your new password.',
        );
    }

    /**
     * Both INVALID_TOKEN and INVALID_USER surface as a 422 on `email`, so a
     * bad token cannot be used to discover whether an address is registered.
     */
    private function rejectToken(string $status): JsonResponse
    {
        $message = match ($status) {
            Password::INVALID_TOKEN, Password::INVALID_USER =>
                'This password reset link is invalid or has expired. Please request a new one.',
            Password::RESET_THROTTLED =>
                'Please wait before retrying.',
            default => 'The password could not be reset.',
        };

        return ApiResponse::validationError(['email' => [$message]], $message);
    }

    /**
     * Only meaningful for the database session driver; a no-op otherwise.
     */
    private function purgeSessions(int $userId): void
    {
        if (Config::get('session.driver') !== 'database') {
            return;
        }

        $table = (string) Config::get('session.table', 'sessions');

        try {
            DB::table($table)
                ->where('user_id', $userId)
                ->delete();
        } catch (Throwable $e) {
            // A missing sessions table must not turn a successful reset into a
            // 500 — the password is already changed and the tokens are gone.
            report($e);
        }
    }
}
