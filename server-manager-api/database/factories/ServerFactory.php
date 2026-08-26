<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServerOs;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    /**
     * @var class-string<Server>
     */
    protected $model = Server::class;

    /**
     * Role suffixes, so generated names read like a real estate ("app-01-web")
     * rather than "Server 1..N". Nothing in the application depends on a naming
     * scheme or a count.
     *
     * @var array<int, string>
     */
    private const array ROLES = [
        'web', 'app', 'api', 'db', 'cache', 'queue', 'build', 'mail', 'edge', 'backup',
    ];

    /**
     * @var array<int, string>
     */
    private const array WINDOWS_VERSIONS = [
        'Windows Server 2019', 'Windows Server 2022', 'Windows Server 2025',
    ];

    /**
     * @var array<int, string>
     */
    private const array LINUX_VERSIONS = [
        'Ubuntu 22.04 LTS', 'Ubuntu 24.04 LTS', 'Debian 12', 'Rocky Linux 9', 'Alpine 3.20',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $os = $this->faker->randomElement(ServerOs::values());

        return [
            'name' => sprintf(
                '%s-%02d-%s',
                $this->faker->domainWord(),
                $this->faker->numberBetween(1, 99),
                $this->faker->randomElement(self::ROLES),
            ),
            'hostname' => $this->faker->domainWord().'.'.$this->faker->domainName(),
            // TEST-NET / documentation ranges only, so seeded data never points at
            // a real host.
            'ip_address' => $this->faker->randomElement(['192.0.2.', '198.51.100.', '203.0.113.'])
                .$this->faker->numberBetween(1, 254),
            'os' => $os,
            'os_version' => $this->versionFor($os),
            'hosting_id' => $this->maybeId('hostings', 55),
            'client_id' => $this->maybeId('clients', 60),
            'project_id' => $this->maybeId('projects', 45),
            'monitoring_enabled' => true,
            'heartbeat_interval_seconds' => (int) Config::get('monitoring.heartbeat.default_interval_seconds', 60),
            'heartbeat_grace_seconds' => (int) Config::get('monitoring.heartbeat.default_grace_seconds', 150),
            // Null thresholds mean "use the configured defaults", which is the
            // common case; a minority carry an explicit override.
            'cpu_warning_threshold' => $this->faker->boolean(25) ? $this->faker->numberBetween(60, 85) : null,
            'cpu_critical_threshold' => null,
            'memory_warning_threshold' => null,
            'memory_critical_threshold' => null,
            'disk_warning_threshold' => null,
            'disk_critical_threshold' => null,
            'last_heartbeat_at' => null,
            'agent_version' => null,
            'uptime_seconds' => null,
            'notes' => $this->faker->boolean(30) ? $this->faker->sentence(8) : null,
        ];
    }

    private function versionFor(string $os): ?string
    {
        return match (ServerOs::tryFromValue($os)) {
            ServerOs::Windows => $this->faker->randomElement(self::WINDOWS_VERSIONS),
            ServerOs::Linux => $this->faker->randomElement(self::LINUX_VERSIONS),
            ServerOs::MacOs => 'macOS '.$this->faker->numberBetween(13, 15),
            ServerOs::FreeBsd => 'FreeBSD '.$this->faker->numberBetween(13, 14).'.'.$this->faker->numberBetween(0, 3),
            default => null,
        };
    }

    /**
     * A random existing id from $table, or null. Reads the table directly so
     * seeding attaches to records that already exist and degrades to null (a
     * valid state) when the catalogue is empty.
     */
    private function maybeId(string $table, int $chancePercent): ?int
    {
        if (! $this->faker->boolean($chancePercent)) {
            return null;
        }

        $id = DB::table($table)->whereNull('deleted_at')->inRandomOrder()->value('id');

        return $id === null ? null : (int) $id;
    }

    public function windows(): static
    {
        return $this->state(fn (): array => [
            'os' => ServerOs::Windows->value,
            'os_version' => $this->faker->randomElement(self::WINDOWS_VERSIONS),
        ]);
    }

    public function linux(): static
    {
        return $this->state(fn (): array => [
            'os' => ServerOs::Linux->value,
            'os_version' => $this->faker->randomElement(self::LINUX_VERSIONS),
        ]);
    }

    /**
     * Reporting normally: a heartbeat well inside the tolerance window.
     */
    public function healthy(): static
    {
        return $this->state(fn (): array => [
            'monitoring_enabled' => true,
            'last_heartbeat_at' => now()->subSeconds($this->faker->numberBetween(1, 30)),
            'agent_version' => '1.0.'.$this->faker->numberBetween(0, 9),
            'uptime_seconds' => $this->faker->numberBetween(3_600, 5_000_000),
        ]);
    }

    /**
     * Silent for long enough that `isStale()` is true regardless of the
     * configured interval — derived from the tolerance, not a magic number.
     */
    public function offline(): static
    {
        $tolerance = max(
            (int) Config::get('monitoring.heartbeat.default_grace_seconds', 150),
            (int) Config::get('monitoring.heartbeat.default_interval_seconds', 60) + 30,
        );

        return $this->state(fn (): array => [
            'monitoring_enabled' => true,
            'last_heartbeat_at' => now()->subSeconds($tolerance * 4),
            'agent_version' => '1.0.0',
        ]);
    }

    /**
     * Monitoring on, but no agent has ever reported.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'monitoring_enabled' => true,
            'last_heartbeat_at' => null,
            'agent_version' => null,
            'uptime_seconds' => null,
        ]);
    }

    /**
     * Monitoring switched off.
     */
    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'monitoring_enabled' => false,
        ]);
    }
}
