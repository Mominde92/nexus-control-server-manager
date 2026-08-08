<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

/**
 * Partial update. `password` stays optional — omitting it (or sending it blank)
 * preserves the existing hash rather than clearing it.
 */
final class UpdateUserRequest extends UserRequest
{
    protected bool $partial = true;
}
