<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared skeleton for every module's Store/Update request pair.
 *
 * A module declares its field constraints **once** in `fieldRules()` and names
 * the mandatory subset in `requiredFields()`. This class then derives the
 * presence rules for the two shapes an endpoint accepts:
 *
 *   Store  — required fields must be present; optionals may be null.
 *   Update — every field is `sometimes`, so a PATCH carrying two keys validates
 *            those two and leaves the rest of the record alone.
 *
 * Keeping presence out of `fieldRules()` is what stops the two requests from
 * drifting apart as fields are added.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * True for the Update variant: every field becomes optional-if-absent.
     */
    protected bool $partial = false;

    /**
     * Authorisation is enforced by Policies in the controller, so that a denial
     * answers 403 rather than a validation error.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Field => constraints, *excluding* presence (`required` / `nullable` /
     * `sometimes`), which this class supplies.
     *
     * @return array<string, array<int, mixed>>
     */
    abstract protected function fieldRules(): array;

    /**
     * Fields that must carry a value.
     *
     * @return array<int, string>
     */
    protected function requiredFields(): array
    {
        return [];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->requiredFields();
        $rules = [];

        foreach ($this->fieldRules() as $field => $constraints) {
            $presence = in_array($field, $required, true) ? ['required'] : ['nullable'];

            if ($this->partial) {
                array_unshift($presence, 'sometimes');
            }

            $rules[$field] = array_merge($presence, $constraints);
        }

        return $rules;
    }

    /**
     * The record being updated, for `Rule::unique()->ignore()`.
     *
     * Resolved from whichever route parameter the route file used, so this keeps
     * working whether the binding is `{user}`, `{client}` or a bare `{id}`.
     */
    protected function routeModelId(): ?int
    {
        foreach ($this->route()?->parameters() ?? [] as $value) {
            if (is_numeric($value)) {
                return (int) $value;
            }

            if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                return (int) $value->getKey();
            }
        }

        return null;
    }

    /**
     * Trim every submitted string and collapse blanks to null, so `""` from an
     * emptied form field does not fail a `max:` or `url` rule.
     *
     * @param  array<int, string>  $lowercase  Keys to also lowercase (emails).
     * @param  array<int, string>  $except  Keys to leave byte-for-byte intact
     *                                      (passwords, where whitespace counts).
     */
    protected function normaliseInput(array $lowercase = [], array $except = []): void
    {
        $input = $this->all();

        foreach ($input as $key => $value) {
            if (! is_string($value) || in_array($key, $except, true)) {
                continue;
            }

            $trimmed = trim($value);
            $input[$key] = $trimmed === '' ? null : $trimmed;
        }

        foreach ($lowercase as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = mb_strtolower($input[$key]);
            }
        }

        $this->replace($input);
    }
}
