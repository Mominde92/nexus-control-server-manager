<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Provider;

/**
 * Application service for the Provider module.
 *
 * @extends CrudServiceInterface<Provider>
 */
interface ProviderServiceInterface extends CrudServiceInterface
{
    /**
     * Fetch including soft-deleted rows so the restore endpoint can authorise
     * against a real model instance.
     */
    public function findWithTrashed(int $id): Provider;

    /**
     * Provider counts keyed by type, plus the active total — dashboard input.
     *
     * @return array{by_type: array<string, int>, active: int, total: int}
     */
    public function statistics(): array;
}
