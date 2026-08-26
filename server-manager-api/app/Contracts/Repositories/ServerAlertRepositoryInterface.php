<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\ServerAlert;

/**
 * Alert persistence.
 *
 * @extends RepositoryInterface<ServerAlert>
 */
interface ServerAlertRepositoryInterface extends RepositoryInterface
{
    /**
     * Open alert counts keyed by severity value, in ONE grouped query.
     *
     * @return array<string, int>
     */
    public function openCountsBySeverity(): array;

    /**
     * The open alert matching a fingerprint for a server, if any. Single indexed
     * hit on `sa_dedupe_idx` — this is the dedupe lookup ingest performs.
     */
    public function findOpenByFingerprint(int $serverId, string $fingerprint): ?ServerAlert;
}
