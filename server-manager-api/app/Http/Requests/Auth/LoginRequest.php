<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Credentials for both the cookie-session login and the token-issuing endpoint.
 *
 * Validation here is deliberately shape-only (present, well-formed, bounded).
 * Whether the credentials are *correct* is answered by the auth guard, and the
 * failure message is generic so this endpoint cannot be used to enumerate
 * registered email addresses.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            // No password complexity rules on login: the stored password was
            // validated when it was set, and rejecting a "weak" submission here
            // would leak that it failed for a reason other than being wrong.
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],

            // Optional label for the personal access token issued by the
            // token-mode endpoint; ignored by the session endpoint.
            'device_name' => ['sometimes', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => mb_strtolower(trim($this->string('email')->value())),
            ]);
        }
    }

    /**
     * Just the pair the guard needs.
     *
     * @return array{email: string, password: string}
     */
    public function credentials(): array
    {
        return [
            'email' => (string) $this->validated('email'),
            'password' => (string) $this->validated('password'),
        ];
    }

    public function remember(): bool
    {
        return $this->boolean('remember');
    }

    public function deviceName(): string
    {
        $name = trim((string) $this->input('device_name', ''));

        return $name === '' ? 'api-token' : $name;
    }
}
