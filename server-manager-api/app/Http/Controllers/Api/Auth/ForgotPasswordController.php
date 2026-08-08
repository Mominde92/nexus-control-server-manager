<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\ActivityLoggerInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

/**
 * Password reset request.
 *
 * The response is identical whether or not the address is registered, whether or
 * not the mail actually sends, and whether or not the broker throttled the
 * request. Any variation — different message, different status, even a
 * noticeably different response time — hands an attacker a list of valid
 * accounts.
 */
class ForgotPasswordController extends Controller
{
    /**
     * The single answer this endpoint ever gives.
     */
    private const string GENERIC_MESSAGE =
        'If an account matches that email address, a password reset link is on its way.';

    public function __construct(
        private readonly ActivityLoggerInterface $logger,
    ) {}

    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');

        // The broker's return status is intentionally discarded.
        Password::broker()->sendResetLink(['email' => $email]);

        $user = User::query()->where('email', $email)->first();

        $this->logger->logAs(
            ActivityAction::PasswordResetRequested,
            $user?->getKey() === null ? null : (int) $user->getKey(),
            $user?->name,
            $user,
            'Password reset requested.',
            ['email' => $email, 'account_exists' => $user !== null],
        );

        return ApiResponse::success(null, self::GENERIC_MESSAGE);
    }
}
