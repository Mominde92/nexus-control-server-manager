<?php

declare(strict_types=1);

namespace App\Http\Requests\Domain;

use App\Http\Requests\Concerns\AppliesUpdatePresence;
use App\Models\Domain;
use Illuminate\Validation\Rules\Unique;

/**
 * Update a domain record.
 */
class UpdateDomainRequest extends DomainRequest
{
    use AppliesUpdatePresence;

    /**
     * Re-submitting a record's own name must not trip the uniqueness check.
     */
    protected function uniqueNameRule(): Unique
    {
        return parent::uniqueNameRule()->ignore($this->routeDomainId());
    }

    /**
     * The route parameter is a bound Domain under route-model binding, but may
     * arrive as a bare id (for instance from a test calling the route by name).
     */
    private function routeDomainId(): ?int
    {
        $parameter = $this->route('domain');

        if ($parameter instanceof Domain) {
            return (int) $parameter->getKey();
        }

        return is_numeric($parameter) ? (int) $parameter : null;
    }
}
