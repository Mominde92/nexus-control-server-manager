<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the application's audit trail. Bound to App\Services\ActivityLogger.
 */
interface ActivityLoggerInterface
{
    /**
     * Record an auditable event.
     *
     * @param  array<string, mixed>  $properties  Extra context; credential
     *                                            values must already be
     *                                            redacted by the caller.
     */
    public function log(
        ActivityAction $action,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
    ): ActivityLog;

    /**
     * Record an event attributed to a specific actor, used for events where
     * there is no authenticated session yet (login, failed login, reset).
     *
     * @param  array<string, mixed>  $properties
     */
    public function logAs(
        ActivityAction $action,
        ?int $userId,
        ?string $userName,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
    ): ActivityLog;

    /**
     * Temporarily suspend logging — used by seeders and bulk imports so the
     * audit trail is not flooded with machine-generated noise.
     */
    public function withoutLogging(callable $callback): mixed;

    public function isEnabled(): bool;
}
