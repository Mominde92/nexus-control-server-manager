<?php

declare(strict_types=1);

namespace App\Http\Requests\Provider;

final class StoreProviderRequest extends ProviderRequest
{
    protected bool $partial = false;
}
