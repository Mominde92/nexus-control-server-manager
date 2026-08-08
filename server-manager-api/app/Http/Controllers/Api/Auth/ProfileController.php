<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Self-service profile edit.
 *
 * The change is audited automatically: User uses the LogsActivity concern, so the
 * update writes a field-level diff to the trail without an explicit log call.
 */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Only the four whitelisted keys reach fill(); the request rules are the
        // allow-list, and every key here is fillable on User.
        $user->fill($request->safe()->only(['name', 'email', 'phone', 'job_title']));
        $user->save();

        $user->loadMissing(AuthUserResource::relations());

        return ApiResponse::success(
            AuthUserResource::make($user)->resolve(),
            'Profile updated successfully.',
        );
    }
}
