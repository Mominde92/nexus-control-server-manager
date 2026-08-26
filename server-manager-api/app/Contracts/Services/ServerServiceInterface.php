<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Server;
use App\Models\ServerAgentCredential;

/**
 * Server business rules.
 *
 * @extends CrudServiceInterface<Server>
 */
interface ServerServiceInterface extends CrudServiceInterface
{
    public function findTrashed(int $id): Server;

    public function enableMonitoring(Server $server): Server;

    /**
     * Switch monitoring off and resolve anything currently open, so a
     * deliberately silenced host does not keep an "offline" alert forever.
     */
    public function disableMonitoring(Server $server): Server;

    /**
     * Mint a credential. The plaintext token is returned ONCE and never stored
     * in recoverable form.
     *
     * @return array{credential: ServerAgentCredential, token: string}
     */
    public function issueCredential(Server $server, ?string $name = null): array;

    /**
     * Issue a successor and revoke the predecessor, linking the two via
     * `rotated_from_id` so the audit trail survives the rotation.
     *
     * @return array{credential: ServerAgentCredential, token: string}
     */
    public function rotateCredential(Server $server, ?string $name = null): array;

    public function revokeCredential(ServerAgentCredential $credential, ?string $reason = null): bool;

    /**
     * Agent install steps for this server's platform.
     *
     * Driven by `ServerOs::agentFlavour()` rather than by individual OS cases,
     * so adding an operating system that reuses an existing flavour needs no
     * change here.
     *
     * @return array{
     *     os: string, flavour: string, shell: string,
     *     ingest_url: string, metrics_url: string, config_url: string,
     *     interval_seconds: int, steps: array<int, string>,
     *     install_command: string
     * }
     */
    public function installInstructions(Server $server): array;
}
