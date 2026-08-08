<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Composes a validation ruleset from two independent halves:
 *
 *   - the *value* rules for a field (type, size, enum membership, FK existence),
 *     which are identical whether the record is being created or updated; and
 *   - the *presence* rules (`required` vs `sometimes|nullable`), which are the
 *     only thing that differs between a POST and a PATCH.
 *
 * Store/Update pairs therefore share one description of what a valid value is,
 * while stating their presence contract explicitly.
 */
trait BuildsFieldRules
{
    /**
     * Value rules per field, without any presence rule.
     *
     * @return array<string, array<int, mixed>>
     */
    abstract protected function valueRules(): array;

    /**
     * Fields that must be supplied (subject to the presence strategy).
     *
     * @return array<int, string>
     */
    abstract protected function requiredFields(): array;

    /**
     * Presence rules prepended to a mandatory field.
     *
     * @return array<int, string>
     */
    abstract protected function requiredPresence(): array;

    /**
     * Presence rules prepended to an optional field.
     *
     * @return array<int, string>
     */
    abstract protected function optionalPresence(): array;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->requiredFields();
        $rules = [];

        foreach ($this->valueRules() as $field => $valueRules) {
            $presence = in_array($field, $required, true)
                ? $this->requiredPresence()
                : $this->optionalPresence();

            $rules[$field] = [...$presence, ...$valueRules];
        }

        return $rules;
    }

    /**
     * Trim every string in the payload.
     *
     * Leading/trailing whitespace is never meaningful in any field of these
     * modules, and untrimmed input defeats `size:3` on the currency code and
     * the domain-name regex.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function trimStrings(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value);
            }
        }

        return $input;
    }
}
