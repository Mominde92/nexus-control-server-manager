<?php

declare(strict_types=1);

namespace App\Http\Requests\Provider;

/**
 * Partial update: only the submitted keys are validated and persisted.
 */
final class UpdateProviderRequest extends ProviderRequest
{
    protected bool $partial = true;
}
