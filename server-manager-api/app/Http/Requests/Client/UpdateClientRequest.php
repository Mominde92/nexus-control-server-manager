<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

/**
 * Partial update: only the submitted keys are validated and persisted.
 */
final class UpdateClientRequest extends ClientRequest
{
    protected bool $partial = true;
}
