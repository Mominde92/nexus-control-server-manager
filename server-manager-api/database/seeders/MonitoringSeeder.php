<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MetricType;
use App\Enums\ServerOs;
use App\Models\Server;
use App\Models\ServerAgentCredential;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo monitoring estate.
 *
 * The server count is DERIVED, not written down: it comes from
 * MONITORING_DEMO_SERVERS, so the same seeder produces 3 servers or 300 without
 * an edit. There is deliberately no list of named servers here — that would bake
 * an assumed inventory into the codebase, which is exactly what the monitoring
 * module is designed not to have.
 *
 * The OS mix is drawn from ServerOs::values(), so a newly supported platform is
 * represented automatically.
 */
final class MonitoringSeeder extends Seeder
{
    /**
     * Hours of metric history generated per server.
     */
    private const int HISTORY_HOURS = 2;

    /**
     * Sample interval for the generated history, in seconds.
     */
    private const int SAMPLE_INTERVAL = 300;

    /**
     * Rows per bulk insert.
     */
    private const int INSERT_CHUNK = 1000;

    public function run(): void
    {
        $count = $this->desiredCount();

        if ($count < 1) {
            $this->command?->info('MONITORING_DEMO_SERVERS is 0 — skipping demo servers.');

            return;
        }

        $existing = Server::query()->count();

        if ($existing >= $count) {
            $this->command?->info("Monitoring demo data already present ({$existing} server(s)); skipping.");

            return;
        }

        $osValues = ServerOs::values();
        $created = [];

        for ($index = $existing; $index < $count; $index++) {
            // Round-robin the OS list so every supported platform appears, and a
            // new enum case is covered without touching this seeder.
            $os = $osValues[$index % count($osValues)];

            $state = match ($index % 5) {
                // A deliberate spread across the health states the dashboard
                // renders, derived from the position rather than hardcoded rows.
                0 => 'pending',
                4 => 'offline',
                default => 'healthy',
            };

            /** @var Server $server */
            $server = Server::factory()
                ->{$state}()
                ->create(['os' => $os, 'os_version' => null]);

            $created[] = $server;

            // Every server gets a credential, so the demo can exercise real agent
            // authentication rather than a bypass.
            $issued = ServerAgentCredential::issue($server, 'Seeded agent');
            $issued['credential']->save();
        }

        $this->seedMetrics($created);

        $this->command?->info(sprintf(
            'Seeded %d monitored server(s) across %d operating system(s), with ~%dh of metrics each.',
            count($created),
            count($osValues),
            self::HISTORY_HOURS,
        ));
    }

    /**
     * How many servers the demo should contain.
     *
     * Read from the environment with a modest default. Never a literal list.
     */
    private function desiredCount(): int
    {
        return max(0, (int) env('MONITORING_DEMO_SERVERS', 6));
    }

    /**
     * Generate recent metric history with bulk inserts.
     *
     * Written as batched `insert()` calls rather than a loop of `create()` calls
     * for the same reason ingest is: one statement per chunk instead of one query
     * per sample.
     *
     * @param  array<int, Server>  $servers
     */
    private function seedMetrics(array $servers): void
    {
        if ($servers === []) {
            return;
        }

        $types = [
            MetricType::CpuPercent,
            MetricType::MemoryPercent,
            MetricType::DiskPercent,
            MetricType::LoadAverage,
        ];

        $samplesPerType = (int) ((self::HISTORY_HOURS * 3600) / self::SAMPLE_INTERVAL);
        $now = now();
        $buffer = [];

        foreach ($servers as $server) {
            // A pending server has never reported, so it must have no samples —
            // otherwise the seeded data would contradict its own health state.
            if ($server->last_heartbeat_at === null) {
                continue;
            }

            foreach ($types as $type) {
                // A per-server baseline means some hosts sit comfortably low and
                // others drift into warning territory, so the alert pipeline has
                // something real to react to.
                $baseline = match ($type) {
                    MetricType::CpuPercent => random_int(15, 75),
                    MetricType::MemoryPercent => random_int(35, 80),
                    MetricType::DiskPercent => random_int(40, 88),
                    default => random_int(1, 4),
                };

                for ($step = $samplesPerType; $step > 0; $step--) {
                    $value = $type->isPercentage()
                        ? min(99.9, max(0.5, $baseline + random_int(-8, 8)))
                        : max(0.05, $baseline + (random_int(-100, 100) / 100));

                    $recordedAt = $now->copy()->subSeconds($step * self::SAMPLE_INTERVAL);

                    $buffer[] = [
                        'server_id' => $server->getKey(),
                        'metric_type' => $type->value,
                        'metric_key' => $type->value,
                        'value' => $value,
                        'recorded_at' => $recordedAt->toDateTimeString(),
                        'created_at' => $recordedAt->toDateTimeString(),
                    ];

                    if (count($buffer) >= self::INSERT_CHUNK) {
                        DB::table('server_metrics')->insert($buffer);
                        $buffer = [];
                    }
                }
            }

            // A matching heartbeat row, so the history and the cursor agree.
            DB::table('server_heartbeats')->insert([
                'server_id' => $server->getKey(),
                'received_at' => $server->last_heartbeat_at->toDateTimeString(),
                'agent_version' => $server->agent_version,
                'uptime_seconds' => $server->uptime_seconds,
                'source_ip' => '192.0.2.1',
                'payload' => json_encode(['seeded' => true], JSON_THROW_ON_ERROR),
                'created_at' => $server->last_heartbeat_at->toDateTimeString(),
            ]);
        }

        if ($buffer !== []) {
            DB::table('server_metrics')->insert($buffer);
        }
    }
}
