import apiClient, { unwrap } from '@/lib/api-client'
import type { ApiEnvelope } from '@/types/api'
import type { MonitoringSummary, ServerSummary } from '@/types/models'

/**
 * Raw shape returned by `GET /monitoring/summary`.
 *
 * The API groups counters by concern (`servers`, `alerts`); the UI renders one
 * flat row of tiles. Normalising here — rather than teaching every component
 * about the nesting — keeps the API free to regroup without a UI change.
 */
interface RawMonitoringSummary {
  servers?: {
    total?: number
    monitored?: number
    healthy?: number
    warning?: number
    critical?: number
    offline?: number
    pending?: number
    disabled?: number
  }
  alerts?: {
    total?: number
    critical?: number
    warning?: number
    info?: number
  }
  needs_attention?: number
}

/**
 * Flatten the nested payload, defaulting every counter to 0.
 *
 * Tolerates an already-flat payload too, so the client keeps working if the API
 * is simplified later.
 */
function normaliseSummary(raw: RawMonitoringSummary & Partial<MonitoringSummary>): MonitoringSummary {
  const servers = raw.servers ?? {}
  const alerts = raw.alerts ?? {}

  const pick = (nested: number | undefined, flat: number | undefined): number =>
    nested ?? flat ?? 0

  const warning = pick(servers.warning, raw.warning)
  const critical = pick(servers.critical, raw.critical)
  const offline = pick(servers.offline, raw.offline)

  return {
    total: pick(servers.total, raw.total),
    monitored: pick(servers.monitored, raw.monitored),
    healthy: pick(servers.healthy, raw.healthy),
    warning,
    critical,
    offline,
    pending: pick(servers.pending, raw.pending),
    disabled: pick(servers.disabled, raw.disabled),
    open_alerts: pick(alerts.total, raw.open_alerts),
    // Derived when absent so a flat payload still drives the navbar badge.
    needs_attention: raw.needs_attention ?? critical + warning + offline,
  }
}

/**
 * Fleet-wide monitoring reads.
 *
 * Both endpoints are aggregates computed by the API, so the dashboard costs two
 * requests no matter how many servers exist.
 */
export const monitoringService = {
  async summary(): Promise<MonitoringSummary> {
    const raw = unwrap(
      await apiClient.get<ApiEnvelope<RawMonitoringSummary>>('/monitoring/summary'),
    )

    return normaliseSummary(raw)
  },

  /** Servers needing attention, worst first — ordering comes from the API. */
  async attention(): Promise<ServerSummary[]> {
    return unwrap(
      await apiClient.get<ApiEnvelope<ServerSummary[]>>('/monitoring/attention'),
    )
  },
}

export default monitoringService
