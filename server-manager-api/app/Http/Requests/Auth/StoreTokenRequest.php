<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mint a personal access token for a non-browser client.
 *
 * Abilities are constrained to the application's own permission vocabulary (or
 * the `*` wildcard), so a token can never be granted an ability that does not
 * exist — and, combined with the policy layer, never more than its owner holds.
 */
class StoreTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['sometimes', 'array', 'max:60'],
            'abilities.*' => ['string', Rule::in([...Permission::values(), '*'])],
        ];
    }

    /**
     * Default to the wildcard: an unqualified token acts as its owner, whose
     * own permissions still gate every request.
     *
     * @return array<int, string>
     */
    public function abilities(): array
    {
        /** @var array<int, string> $abilities */
        $abilities = $this->validated('abilities', []);

        return $abilities === [] ? ['*'] : array_values(array_unique($abilities));
    }
}
