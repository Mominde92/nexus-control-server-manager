<?php

declare(strict_types=1);

namespace App\Http\Requests\Domain;

use App\Http\Requests\Concerns\AppliesStorePresence;

/**
 * Create a domain record.
 */
class StoreDomainRequest extends DomainRequest
{
    use AppliesStorePresence;
}
