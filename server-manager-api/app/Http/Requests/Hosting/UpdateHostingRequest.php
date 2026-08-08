<?php

declare(strict_types=1);

namespace App\Http\Requests\Hosting;

use App\Http\Requests\Concerns\AppliesUpdatePresence;

/**
 * Update a hosting record.
 *
 * Every field is `sometimes`, so a PATCH may carry a single key. Omitting a
 * password leaves the stored ciphertext untouched (see HostingService).
 */
class UpdateHostingRequest extends HostingRequest
{
    use AppliesUpdatePresence;
}
