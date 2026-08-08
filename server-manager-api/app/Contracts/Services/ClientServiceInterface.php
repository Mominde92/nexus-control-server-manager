<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Client;

/**
 * Application service for the Client module.
 *
 * @extends CrudServiceInterface<Client>
 */
interface ClientServiceInterface extends CrudServiceInterface
{
    /**
     * Fetch including soft-deleted rows so the restore endpoint can authorise
     * against a real model instance.
     */
    public function findWithTrashed(int $id): Client;
}
