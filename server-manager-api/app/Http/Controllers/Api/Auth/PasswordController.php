<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\ActivityLoggerInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Change your own password.
 *
 * Like a reset, this revokes every *other* credential — all personal access
 * tokens and all other browser sessions — while keeping the caller signed in.
 * A password change is usually a response to a suspected compromise, so leaving
 * the old credentials alive would defeat the point.
 */
class PasswordController extends Controller
{
    public function __construct(
        private readonly ActivityLoggerInterface $logger,
    ) {}

    public function update(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => (string) $request->validated('password'),
            // Retires any outstanding "remember me" cookies.
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();

        $this->purgeOtherSessions($request, (int) $user->getKey());

        $this->logger->log(
            ActivityAction::PasswordChanged,
            $user,
            'Password changed; other sessions and tokens revoked.',
        );

        return ApiResponse::success(null, 'Password changed successfully.');
    }

    /**
     * Delete this user's session rows except the current one.
     *
     * `logoutOtherDevices` is not used because it depends on the
     * AuthenticateSession middleware being active on the route; deleting the rows
     * is unconditional and takes effect on the very next request.
     */
    private function purgeOtherSessions(UpdatePasswordRequest $request, int $userId): void
    {
        if (Config::get('session.driver') !== 'database') {
            // File/array/redis session stores are not indexed by user, so there
            // is nothing to enumerate. The rotated `remember_token` plus the
            // revoked API tokens still cut off the other credentials.
            return;
        }

        $table = (string) Config::get('session.table', 'sessions');
        $currentId = $request->hasSession() ? $request->session()->getId() : null;

        try {
            DB::table($table)
                ->where('user_id', $userId)
                ->when($currentId !== null, fn ($query) => $query->where('id', '!=', $currentId))
                ->delete();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
