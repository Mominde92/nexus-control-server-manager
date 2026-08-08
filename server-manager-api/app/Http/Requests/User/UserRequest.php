<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

/**
 * Field constraints for the User module, shared by Store and Update.
 *
 * `role` is validated here but is not a column — the service applies it through
 * Spatie's `syncRoles()`. `password` is accepted in plain text and hashed by the
 * model's `hashed` cast; it is never echoed back by any Resource.
 */
abstract class UserRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function fieldRules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'email' => ['string', 'email:rfc', 'max:255', $this->uniqueEmail()],
            'phone' => ['string', 'max:32'],
            'job_title' => ['string', 'max:255'],
            'avatar_path' => ['string', 'max:255'],
            'status' => ['string', Rule::in(UserStatus::values())],
            'role' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'password' => ['string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function requiredFields(): array
    {
        return ['name', 'email', 'status', 'role'];
    }

    /**
     * Live rows only: a soft-deleted account must not permanently reserve its
     * email address.
     */
    private function uniqueEmail(): Unique
    {
        return Rule::unique('users', 'email')
            ->whereNull('deleted_at')
            ->ignore($this->routeModelId());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'job_title' => 'job title',
            'avatar_path' => 'avatar',
        ];
    }

    protected function prepareForValidation(): void
    {
        // `password` is excluded from trimming: leading/trailing whitespace is a
        // legitimate part of a passphrase.
        $this->normaliseInput(lowercase: ['email'], except: ['password', 'password_confirmation']);

        // An empty password field means "unchanged", not "set it to blank".
        if ($this->input('password') === '') {
            $this->merge(['password' => null]);
        }
    }
}
