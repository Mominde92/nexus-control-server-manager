<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ActivityLoggerInterface;
use App\Contracts\Repositories\ServerRepositoryInterface;
use App\Contracts\Services\MonitoringServiceInterface;
use App\Contracts\Services\ServerServiceInterface;
use App\Enums\ActivityAction;
use App\Models\Server;
use App\Models\ServerAgentCredential;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Server business rules.
 *
 * @extends BaseCrudService<Server>
 */
class ServerService extends BaseCrudService implements ServerServiceInterface
{
    public function __construct(
        private readonly ServerRepositoryInterface $servers,
        private readonly MonitoringServiceInterface $monitoring,
        private readonly ActivityLoggerInterface $activity,
    ) {
        parent::__construct($servers);
    }

    // -----------------------------------------------------------------------
    // Lifecycle hooks
    // -----------------------------------------------------------------------

    /**
     * Normalise input shared by create and update.
     *
     * Thresholds are the interesting case: a null override means "use the
     * configured default", so an explicitly cleared field must stay null rather
     * than being coerced to 0 (which would alert on every sample).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        foreach (['hostname', 'ip_address', 'os_version', 'notes'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $trimmed = trim($data[$key]);
                $data[$key] = $trimmed === '' ? null : $trimmed;
            }
        }

        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareForCreate(array $data): array
    {
        $data = $this->prepare($data);

        // Intervals default from config, never from a literal in code, so the
        // operator's chosen policy applies to every newly registered host.
        $data['heartbeat_interval_seconds'] ??= (int) Config::get(
            'monitoring.heartbeat.default_interval_seconds',
            60,
        );
        $data['heartbeat_grace_seconds'] ??= (int) Config::get(
            'monitoring.heartbeat.default_grace_seconds',
            150,
        );

        return $data;
    }

    protected function afterCreate(Model $model, array $data): void
    {
        unset($model, $data);

        MonitoringService::flushSummaryCache();
    }

    protected function afterUpdate(Model $model, array $data): void
    {
        unset($model, $data);

        MonitoringService::flushSummaryCache();
    }

    public function findTrashed(int $id): Server
    {
        $server = $this->servers->findWithTrashed($id);

        if ($server === null) {
            throw (new ModelNotFoundException)
                ->setModel(Server::class, [$id]);
        }

        return $server;
    }

    // -----------------------------------------------------------------------
    // Monitoring toggle
    // -----------------------------------------------------------------------

    public function enableMonitoring(Server $server): Server
    {
        return DB::transaction(function () use ($server): Server {
            $server->forceFill(['monitoring_enabled' => true])->save();

            $this->activity->log(
                ActivityAction::Updated,
                $server,
                'Enabled monitoring.',
                ['monitoring_enabled' => true],
            );

            MonitoringService::flushSummaryCache();

            return $server->refresh();
        });
    }

    /**
     * Switch monitoring off and close anything currently open.
     *
     * Resolving the open alerts matters: a host that has been deliberately
     * silenced must not leave a permanent "offline" incident on the dashboard,
     * because that would train operators to ignore real ones.
     */
    public function disableMonitoring(Server $server): Server
    {
        return DB::transaction(function () use ($server): Server {
            $server->forceFill(['monitoring_enabled' => false])->save();

            $resolved = $this->monitoring instanceof MonitoringService
                ? $this->monitoring->resolveAllAlertsFor($server, 'Monitoring disabled.')
                : 0;

            $this->activity->log(
                ActivityAction::Updated,
                $server,
                'Disabled monitoring.',
                ['monitoring_enabled' => false, 'alerts_resolved' => $resolved],
            );

            MonitoringService::flushSummaryCache();

            return $server->refresh();
        });
    }

    // -----------------------------------------------------------------------
    // Agent credentials
    // -----------------------------------------------------------------------

    /**
     * Mint a credential.
     *
     * The plaintext token is returned to the caller exactly once; only a SHA-256
     * hash of the secret half is persisted, so it can never be read back — not
     * from the database, not from the audit trail, not from this API.
     *
     * @return array{credential: ServerAgentCredential, token: string}
     */
    public function issueCredential(Server $server, ?string $name = null): array
    {
        return DB::transaction(function () use ($server, $name): array {
            $issued = ServerAgentCredential::issue($server, $name, Auth::id());
            $issued['credential']->save();

            // The token itself is never logged — only that one was minted.
            $this->activity->log(
                ActivityAction::Created,
                $server,
                'Issued a monitoring agent credential.',
                [
                    'credential_id' => (int) $issued['credential']->getKey(),
                    'token_id' => $issued['credential']->token_id,
                    'name' => $name,
                ],
            );

            return $issued;
        });
    }

    /**
     * Issue a successor and revoke the predecessor in one transaction.
     *
     * The new credential points back at the old one via `rotated_from_id`, so
     * the chain of custody survives rotation. Revoking inside the same
     * transaction means there is never a window with two live tokens.
     *
     * @return array{credential: ServerAgentCredential, token: string}
     */
    public function rotateCredential(Server $server, ?string $name = null): array
    {
        return DB::transaction(function () use ($server, $name): array {
            /** @var ServerAgentCredential|null $current */
            $current = ServerAgentCredential::query()
                ->where('server_id', $server->getKey())
                ->active()
                ->latest('id')
                ->first();

            $issued = ServerAgentCredential::issue($server, $name, Auth::id());
            $issued['credential']->rotated_from_id = $current?->getKey();
            $issued['credential']->save();

            $current?->revoke('Rotated.', Auth::id());

            $this->activity->log(
                ActivityAction::Updated,
                $server,
                'Rotated the monitoring agent credential.',
                [
                    'new_credential_id' => (int) $issued['credential']->getKey(),
                    'revoked_credential_id' => $current === null ? null : (int) $current->getKey(),
                ],
            );

            return $issued;
        });
    }

    public function revokeCredential(ServerAgentCredential $credential, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($credential, $reason): bool {
            $revoked = $credential->revoke($reason, Auth::id());

            if ($revoked) {
                $this->activity->log(
                    ActivityAction::Deleted,
                    $credential->server,
                    'Revoked a monitoring agent credential.',
                    [
                        'credential_id' => (int) $credential->getKey(),
                        'reason' => $reason,
                    ],
                );
            }

            return $revoked;
        });
    }

    // -----------------------------------------------------------------------
    // Agent install instructions
    // -----------------------------------------------------------------------

    /**
     * OS-appropriate install steps.
     *
     * Branches on `ServerOs::agentFlavour()`, never on individual OS cases: a
     * newly supported operating system that reuses an existing flavour (say,
     * another POSIX platform) gets working instructions with no change here.
     *
     * @return array{
     *     os: string, flavour: string, shell: string,
     *     ingest_url: string, metrics_url: string, config_url: string,
     *     interval_seconds: int, steps: array<int, string>,
     *     install_command: string
     * }
     */
    public function installInstructions(Server $server): array
    {
        $flavour = $server->os->agentFlavour();
        $base = rtrim((string) Config::get('app.url'), '/');
        $heartbeatUrl = $base.'/api/agent/heartbeat';
        $metricsUrl = $base.'/api/agent/metrics';
        $configUrl = $base.'/api/agent/config';
        $windowsAgentUrl = $base.'/agents/nexus-windows-agent.ps1';
        $interval = $server->heartbeat_interval_seconds
            ?: (int) Config::get('monitoring.heartbeat.default_interval_seconds', 60);

        // The token is deliberately a placeholder: instructions are safe to
        // re-read, so they must never embed a live secret.
        $placeholder = ServerAgentCredential::TOKEN_PREFIX.'_<token-id>_<secret>';

        [$shell, $steps, $command] = match ($flavour) {
            'windows-powershell' => [
                'powershell',
                [
                    'Open PowerShell as Administrator on the server.',
                    'Run the install command below; it downloads the signed-in installation agent and asks for the token securely.',
                    'The installer stores the token as a machine-scoped environment variable and creates a SYSTEM scheduled task.',
                    'Confirm the first heartbeat appears in the dashboard; health moves from "Awaiting first heartbeat" to "Healthy".',
                ],
                sprintf(
                    '$agent = Join-Path $env:TEMP "nexus-windows-agent.ps1"; '
                    .'Invoke-WebRequest -Uri "%s" -OutFile $agent; '
                    .'powershell.exe -NoProfile -ExecutionPolicy Bypass -File $agent -Install -ApiUrl "%s/api"',
                    $windowsAgentUrl,
                    $base,
                ),
            ],

            'posix-shell' => [
                'sh',
                [
                    'Sign in to the server over SSH with an account that can create a service.',
                    'Export the agent token from a root-only file (chmod 600) rather than inlining it in the unit or crontab.',
                    'Register the agent with systemd, launchd or cron at the interval below.',
                    'Confirm the first heartbeat appears in the dashboard; health moves from "Awaiting first heartbeat" to "Healthy".',
                ],
                sprintf(
                    'export SM_AGENT_TOKEN=%s; curl -fsS -X POST %s '
                    .'-H "Authorization: Bearer $SM_AGENT_TOKEN" '
                    .'-H "Content-Type: application/json" '
                    .'-d \'{"agent_version":"1.0.0","uptime_seconds":0}\'',
                    $placeholder,
                    $heartbeatUrl,
                ),
            ],

            // Portable fallback: any platform that can make an HTTPS request can
            // be monitored today, without waiting for a bespoke agent.
            default => [
                'any',
                [
                    'Any runtime that can issue an authenticated HTTPS POST can act as an agent.',
                    'Send a heartbeat at the interval below, and post metric samples in the same shape.',
                    'Fetch the config endpoint at startup so the interval and requested metrics come from the server, not from the agent build.',
                    'Confirm the first heartbeat appears in the dashboard.',
                ],
                sprintf(
                    'POST %s  Authorization: Bearer %s  Content-Type: application/json'
                    .'  {"agent_version":"1.0.0","uptime_seconds":0}',
                    $heartbeatUrl,
                    $placeholder,
                ),
            ],
        };

        return [
            'os' => $server->os->value,
            'flavour' => $flavour,
            'shell' => $shell,
            'ingest_url' => $heartbeatUrl,
            'metrics_url' => $metricsUrl,
            'config_url' => $configUrl,
            'interval_seconds' => (int) $interval,
            'steps' => $steps,
            'install_command' => $command,
        ];
    }
}
