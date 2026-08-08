<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

final class StoreClientRequest extends ClientRequest
{
    protected bool $partial = false;
}
