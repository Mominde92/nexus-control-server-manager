<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Marks a model as holding encrypted vendor credentials.
 *
 * Implementers list their secret attributes once; the API layer uses that list
 * to (a) keep secrets out of every normal response, (b) redact them from audit
 * log diffs, and (c) serve them only through the dedicated reveal endpoint
 * guarded by the `credential.view` permission.
 */
trait HasCredentials
{
    /**
     * Attribute names on this model that hold an encrypted secret.
     *
     * @return array<int, string>
     */
    public function credentialAttributes(): array
    {
        /** @var array<int, string> */
        return property_exists($this, 'credentials') ? $this->credentials : [];
    }

    /**
     * Decrypted secrets, keyed by attribute name. Only ever called from a
     * controller action that has already authorised `credential.view`.
     *
     * @return array<string, string|null>
     */
    public function revealCredentials(): array
    {
        $revealed = [];

        foreach ($this->credentialAttributes() as $attribute) {
            $revealed[$attribute] = $this->getAttribute($attribute);
        }

        return $revealed;
    }

    /**
     * Replace secret values in an attribute array with a placeholder, so they
     * never reach logs, exports or error reports.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function redactCredentials(array $attributes): array
    {
        foreach ($this->credentialAttributes() as $attribute) {
            if (array_key_exists($attribute, $attributes)) {
                $attributes[$attribute] = '••••••••';
            }
        }

        return $attributes;
    }
}
