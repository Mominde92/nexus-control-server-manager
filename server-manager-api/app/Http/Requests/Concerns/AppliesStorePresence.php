<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Presence strategy for a create request: mandatory fields must be present,
 * optional ones may be omitted or explicitly null.
 */
trait AppliesStorePresence
{
    /**
     * @return array<int, string>
     */
    protected function requiredPresence(): array
    {
        return ['required'];
    }

    /**
     * @return array<int, string>
     */
    protected function optionalPresence(): array
    {
        return ['nullable'];
    }
}
