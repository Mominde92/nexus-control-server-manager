<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Presence strategy for a partial update: every field may be omitted, but a
 * field the client *does* send must still satisfy its contract — a mandatory
 * field cannot be blanked out.
 */
trait AppliesUpdatePresence
{
    /**
     * @return array<int, string>
     */
    protected function requiredPresence(): array
    {
        return ['sometimes', 'required'];
    }

    /**
     * @return array<int, string>
     */
    protected function optionalPresence(): array
    {
        return ['sometimes', 'nullable'];
    }
}
