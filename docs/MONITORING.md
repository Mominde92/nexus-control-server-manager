# Server Monitoring

Agent-based monitoring for an **unbounded, dynamic** fleet of servers across any
operating system.

---

## 1. Design principle: no fixed server count, anywhere

The requirement that shaped every decision below: **the number of servers is
never fixed, assumed, or hardcoded.** There is no `Server 1..N`, no per-server
config file, no constant array of hosts.

```
Servers  (rows in the `servers` table, created at runtime via the API)
   ├── Server A   (Linux)
   ├── Server B   (Windows Server)
   ├── Server C   (macOS)
   ├── …
   └── Server N   (any OS, including ones not yet supported)
```

Concretely, this means:

| Action | Schema change? | Code change? | Frontend change? |
| --- | --- | --- | --- |
| Add a server | No | No | No |
| Remove / disable a server | No | No | No |
| Add a new metric kind | No | Enum case only | No |
| Support a new OS | No | Enum case + an agent | No |
| Scale from 5 to 5,000 servers | No | No | No |

`config/monitoring.php` holds **policy** knobs only (retention, intervals,
thresholds, query ceilings) — never an inventory.

### Dynamic discovery

The frontend never contains a server list. Every screen reads from paginated
endpoints, so a server created through the API appears on the next fetch, and a
disabled or deleted one disappears. No configuration, no redeploy.

---

## 2. Data model

```
hostings ──┐ (optional link; nullOnDelete)
           ▼
        servers ──┬──< server_agent_credentials   (hashed bearer tokens)
                  ├──< server_heartbeats          (append-only, pruned)
                  ├──< server_metrics             (raw samples, short retention)
                  ├──< server_metric_rollups      (hourly + daily aggregates)
                  └──< server_alerts              (stateful incidents)
```

A server may link to an existing Hosting record, so an account already tracked in
Nexus Control can be promoted to a monitored host without duplicating its
details. The link is `nullOnDelete`: removing a hosting record never silently
destroys monitoring history.

### Metrics are stored *long*, not *wide*

This is the decision that makes new metrics free:

```sql
-- long (what we do)                    -- wide (what we avoid)
server_metrics                          server_metrics
  server_id                               server_id
  metric_type   -- 'cpu_percent'          cpu_percent
  metric_key    -- original agent name    memory_percent
  value                                   disk_percent
  recorded_at                             …ALTER TABLE for every new metric
```

An agent may report a metric the platform has never heard of. Ingest stores it
under `MetricType::Custom` with the original name preserved in `metric_key` — it
is **never rejected and never requires a migration**.

---

## 3. Scaling: how cost is kept flat

Naively, "show me all servers and their health" is an N+1 and a full scan of the
largest tables. Five mechanisms prevent that.

### 3.1 Heartbeat cursor

Liveness never reads `server_heartbeats`. Each check-in also updates a
denormalised `servers.last_heartbeat_at`, so "is it up?" is one indexed read on
a table with one row per server. Dashboard cost therefore scales with **server
count**, not with accumulated history.

### 3.2 Raw + rollups + configurable retention

```
server_metrics         raw samples          retention: 7 days   (default)
server_metric_rollups  hourly buckets       retention: 90 days
server_metric_rollups  daily buckets        retention: 730 days
```

A 90-day CPU chart is ~130,000 raw rows at a 60s sample rate. `monitoring:rollup`
folds raw samples into fixed buckets once, so that chart reads ~90 daily rows
instead. The bucket is chosen automatically from `config('monitoring.resolution')`
based on the requested span:

| Requested range | Source | Bucket |
| --- | --- | --- |
| ≤ 6 hours | raw | none (full resolution) |
| ≤ 48 hours | raw | hourly |
| ≤ 90 days | rollups | hourly |
| longer | rollups | daily |

`min`/`max` are stored beside `avg`, because averaging away a 100% CPU spike
discards exactly the signal an operator needs. `samples` allows correct weighted
re-aggregation (hourly → daily) without returning to the raw table.

Rollups are **idempotent** — a unique key on
`(server_id, metric_type, metric_key, period, bucket_start)` means re-running the
job updates buckets in place instead of duplicating them.

### 3.3 Query ceilings

`config('monitoring.limits')` caps what a single request can ask for:
`max_metric_points` (default 1000) bounds any series response, and
`max_batch_metrics` bounds an ingest batch. **The frontend cannot request all
historical metrics for all servers** — the API will not serve it.

### 3.4 Aggregate, never iterate

Cross-server numbers are computed in SQL. The dashboard summary is a single
aggregate query with `CASE`/`SUM`, not a loop over models; the paginated server
list attaches each row's worst open-alert severity via a subquery, so rendering
15 servers costs a constant number of queries whether the fleet is 10 or 10,000.

### 3.5 Work off the request path

Threshold evaluation and alerting run in a **queued job** after ingest, so
heartbeat latency stays flat as the fleet grows. Rollups, pruning and
offline-detection are scheduled commands, not request-time work.

---

## 4. Agent authentication

Each server gets its **own** credential, independently revocable and rotatable.

```
Token format:  sma_<tokenId>_<secret>
                    │          └── 40 random chars, stored as SHA-256 only
                    └── 16-char public id, UNIQUE + indexed
```

Two properties matter:

- **Not recoverable.** Unlike the vendor passwords elsewhere in this app — which
  are *encrypted* because an operator must read them back — an agent token is a
  bearer secret nobody needs to recover. It is stored as a one-way hash and shown
  **exactly once** at issue time. A database leak yields no usable agent tokens.
- **Constant-cost verification.** The public `token_id` gives one indexed lookup;
  only then is the secret hashed and compared with `hash_equals()` (constant time,
  so timing cannot be used to recover a token). Authentication never scans and
  hashes against every credential, so ingest cost does not grow with fleet size.

SHA-256 rather than bcrypt is deliberate: the secret is 40 characters of
cryptographic randomness, not a human password, so it needs no brute-force
stretching — and ingest runs on every heartbeat, where bcrypt would add tens of
milliseconds per request.

### Rotation preserves history

Rotating issues a new credential, revokes the old one, and links them via
`rotated_from_id`. Nothing is deleted, so the audit trail of who rotated what
survives, and an overlap window exists while an agent picks up its new token.

Revoking takes effect on the **next request** — as does disabling monitoring,
which the ingest middleware rejects with 403.

---

## 5. Multi-OS agent abstraction

Monitoring logic is OS-agnostic. `ServerOs` is a *label plus an agent-selection
hint*, never a branch point:

```
ServerOs ──> agentFlavour()
  Windows  ──> windows-powershell
  Linux    ──┐
  FreeBSD  ──┼─> posix-shell
  macOS    ──┘
  Other    ──> portable
```

Install instructions branch on **flavour**, not on individual OS cases, so adding
an OS that reuses an existing flavour costs one enum case and nothing else. The
ingest contract is plain JSON over HTTPS, so any language can implement an agent.

Supporting a genuinely new platform:

1. Add a `ServerOs` case (and map it to a flavour).
2. Ship an agent that POSTs to `/api/agent/heartbeat` and `/api/agent/metrics`.

No schema change. No change to storage, alerting, retention or the UI.

---

## 6. Agent contract

All agent endpoints authenticate with `Authorization: Bearer sma_…` and are rate
limited per server.

```http
POST /api/agent/heartbeat
Authorization: Bearer sma_<tokenId>_<secret>

{ "agent_version": "1.0.0", "uptime_seconds": 86400,
  "payload": { "kernel": "6.8.0" } }
```

```http
POST /api/agent/metrics
Authorization: Bearer sma_<tokenId>_<secret>

{ "metrics": [
    { "type": "cpu_percent",    "value": 12.5 },
    { "type": "memory_percent", "value": 61.0 },
    { "type": "queue_depth",    "value": 1234 }   ← unknown: kept as custom
] }
```

```http
GET /api/agent/config      → interval + metrics to report (server-driven,
                             so behaviour is not baked into the agent)
```

An agent needs no knowledge of the fleet — only its own token.

---

## 7. Alerts

Alerts are **stateful incidents**, not one row per breaching sample. A host
pinned at 95% CPU for an hour produces **one** open alert that later resolves,
rather than 60 rows. `fingerprint` (e.g. `metric_threshold:cpu_percent:critical`)
identifies the condition; repeat breaches bump `occurrences` and
`last_observed_at` on the existing open row. That is what stops a flapping host
from flooding the table as the fleet grows.

Thresholds resolve per server first, then fall back to config:

```
servers.cpu_critical_threshold   →  config('monitoring.thresholds.cpu.critical')
```

So a legitimately busy host can be tuned individually, without a deploy and
without silencing the rest of the fleet.

### Derived health

`ServerHealth` is **computed, never stored**, so it cannot drift out of sync:

```
monitoring disabled            → disabled
no heartbeat ever              → pending
heartbeat older than tolerance → offline
worst open alert = critical    → critical
worst open alert = warning     → warning
otherwise                      → healthy
```

Tolerance is `max(grace, interval + 30s)` per server — grace is a floor, not an
addition, so a host reporting every 10 minutes is not declared dead after 150s.

---

## 8. Operations

```bash
php artisan monitoring:rollup --period=hour   # fold raw → buckets (idempotent)
php artisan monitoring:prune                  # enforce retention, in chunks
php artisan monitoring:check-offline          # open/resolve offline alerts
```

Scheduled in `routes/console.php`: rollup hourly, prune daily, offline check
every five minutes. **A queue worker and the scheduler must both be running** —
without them, alerts never fire and raw metrics never get rolled up or pruned.

### Retention tuning

```dotenv
MONITORING_RETENTION_RAW_DAYS=7          # high-resolution window
MONITORING_RETENTION_HOURLY_DAYS=90
MONITORING_RETENTION_DAILY_DAYS=730
MONITORING_RETENTION_HEARTBEAT_DAYS=14
MONITORING_MAX_METRIC_POINTS=1000        # per-response ceiling
MONITORING_HEARTBEAT_INTERVAL=60
```

### Capacity planning

Raw rows/day ≈ `servers × metrics_per_sample × (86400 / interval)`.

At 100 servers × 6 metrics × 60s that is ~864,000 rows/day — which is why raw
retention defaults to 7 days and charts read rollups. If ingest volume becomes
the bottleneck before storage does, move `QUEUE_CONNECTION` to Redis and add
`queue` replicas; the ingest path itself is a bulk insert plus one job dispatch.
