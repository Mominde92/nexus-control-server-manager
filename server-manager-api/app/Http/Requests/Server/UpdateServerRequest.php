<?php

declare(strict_types=1);

namespace App\Http\Requests\Server;

use App\Http\Requests\Concerns\AppliesUpdatePresence;

/**
 * Retune a server. Every field is `sometimes`, so a PATCH carrying two keys
 * validates those two and leaves the rest of the record untouched.
 */
class UpdateServerRequest extends ServerRequest
{
    use AppliesUpdatePresence;
}
