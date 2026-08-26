<?php

declare(strict_types=1);

namespace App\Http\Requests\Server;

use App\Http\Requests\Concerns\AppliesStorePresence;

/**
 * Register a server. Only the name is mandatory — everything else either has a
 * database default, is genuinely optional, or falls back to config.
 */
class StoreServerRequest extends ServerRequest
{
    use AppliesStorePresence;
}
