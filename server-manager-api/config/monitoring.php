<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Server Monitoring
|--------------------------------------------------------------------------
|
| Everything here is a *policy* knob, never a server inventory. There is no
| server list, count, or per-server configuration in this file — servers are
| rows in the `servers` table, created and removed through the API at runtime.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Heartbeats
    |----------------------------------------------------------------------
    |
    | Defaults for newly registered servers; each server stores its own
    | interval/grace so a slow or bandwidth-limited host can be tuned
    | individually without a deploy.
    |
    */

    'heartbeat' => [
        'default_interval_seconds' => (int) env('MONITORING_HEARTBEAT_INTERVAL', 60),
        // How far past the expected interval before a host counts as offline.
        'default_grace_seconds' => (int) env('MONITORING_HEARTBEAT_GRACE', 150),
    ],

    /*
    |----------------------------------------------------------------------
    | Retention (configurable metric retention)
    |----------------------------------------------------------------------
    |
    | Raw samples are kept briefly for high-resolution charts, then folded into
    | hourly/daily rollups and pruned. Tune these to trade storage for
    | resolution; `monitoring:prune` enforces them.
    |
    */

    'retention' => [
        'raw_days' => (int) env('MONITORING_RETENTION_RAW_DAYS', 7),
        'hourly_days' => (int) env('MONITORING_RETENTION_HOURLY_DAYS', 90),
        'daily_days' => (int) env('MONITORING_RETENTION_DAILY_DAYS', 730),
        'heartbeat_days' => (int) env('MONITORING_RETENTION_HEARTBEAT_DAYS', 14),
        'resolved_alert_days' => (int) env('MONITORING_RETENTION_ALERT_DAYS', 180),
        // Rows deleted per statement, so a large prune never holds a long lock.
        'prune_chunk' => (int) env('MONITORING_PRUNE_CHUNK', 5000),
    ],

    /*
    |----------------------------------------------------------------------
    | Query guards
    |----------------------------------------------------------------------
    |
    | Hard ceilings that stop one request from asking for the whole dataset —
    | the main defence against a client requesting all history for all servers.
    |
    */

    'limits' => [
        // Max points returned by a single metrics query, after bucketing.
        'max_metric_points' => (int) env('MONITORING_MAX_METRIC_POINTS', 1000),
        // Max distinct servers a single batch ingest may cover.
        'max_batch_metrics' => (int) env('MONITORING_MAX_BATCH_METRICS', 200),
        // Default/most page size for server listings.
        'default_page_size' => 15,
        'max_page_size' => 100,
        // Above this many servers the summary endpoint serves cached counts.
        'summary_cache_seconds' => (int) env('MONITORING_SUMMARY_CACHE_SECONDS', 15),
    ],

    /*
    |----------------------------------------------------------------------
    | Automatic bucket selection
    |----------------------------------------------------------------------
    |
    | Maps a requested range to the cheapest source that still has enough
    | resolution. This is what keeps a 2-year chart as cheap as a 1-hour chart.
    |
    */

    'resolution' => [
        // range (hours) => source
        ['max_hours' => 6, 'source' => 'raw', 'bucket' => null],
        ['max_hours' => 48, 'source' => 'raw', 'bucket' => 'hour'],
        ['max_hours' => 24 * 90, 'source' => 'rollup', 'bucket' => 'hour'],
        // Anything longer reads daily rollups.
        ['max_hours' => null, 'source' => 'rollup', 'bucket' => 'day'],
    ],

    /*
    |----------------------------------------------------------------------
    | Default thresholds
    |----------------------------------------------------------------------
    |
    | Fallbacks when a server defines no override. Percentage metrics only.
    |
    */

    'thresholds' => [
        'cpu' => ['warning' => 80, 'critical' => 92],
        'memory' => ['warning' => 85, 'critical' => 95],
        'disk' => ['warning' => 80, 'critical' => 90],
    ],

    /*
    |----------------------------------------------------------------------
    | Agent credentials
    |----------------------------------------------------------------------
    */

    'agent' => [
        // Blank = never expires. Set to force periodic rotation.
        'token_ttl_days' => env('MONITORING_AGENT_TOKEN_TTL_DAYS') === null
            ? null
            : (int) env('MONITORING_AGENT_TOKEN_TTL_DAYS'),
        // Ingest requests per minute, per server.
        'ingest_rate_limit' => (int) env('MONITORING_INGEST_RATE_LIMIT', 120),
        // Only persist last_used_at this often, to avoid a write per heartbeat.
        'touch_last_used_seconds' => 60,
    ],

];
