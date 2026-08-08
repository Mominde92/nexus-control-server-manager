<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload for the dedicated activate/deactivate/suspend endpoint.
 *
 * Status changes are separated from the general update so the UI can toggle an
 * account without re-submitting (and re-validating) the whole profile.
 */
final class ChangeUserStatusRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function fieldRules(): array
    {
        return [
            'status' => ['string', Rule::in(UserStatus::values())],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function requiredFields(): array
    {
        return ['status'];
    }

    public function status(): UserStatus
    {
        return UserStatus::from($this->string('status')->value());
    }
}
