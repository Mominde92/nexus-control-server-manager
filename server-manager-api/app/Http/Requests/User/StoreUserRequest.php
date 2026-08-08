<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

final class StoreUserRequest extends UserRequest
{
    protected bool $partial = false;

    /**
     * A new account must ship with a password meeting the app-wide policy,
     * confirmed via `password_confirmation`.
     *
     * @return array<int, string>
     */
    protected function requiredFields(): array
    {
        return array_merge(parent::requiredFields(), ['password']);
    }
}
