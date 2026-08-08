<?php

declare(strict_types=1);

namespace App\Http\Requests\Hosting;

use App\Http\Requests\Concerns\AppliesStorePresence;

/**
 * Create a hosting record. Only the title is mandatory; every other column
 * either has a database default or is genuinely optional.
 */
class StoreHostingRequest extends HostingRequest
{
    use AppliesStorePresence;
}
