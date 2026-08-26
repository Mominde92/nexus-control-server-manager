import { useQuery } from '@tanstack/react-query'

import { queryKeys } from '@/lib/query-keys'
import { monitoringService } from '@/services/monitoring.service'
import type { ApiError } from '@/types/api'
import type { MonitoringSummary, ServerSummary } from '@/types/models'

/** How often the fleet aggregates refresh. Metrics are never polled. */
export const MONITORING_POLL_MS = 30_000

/**
 * Fleet counters. One request regardless of fleet size, refreshed on an
 * interval so any screen showing it stays live without polling per server.
 */
export function useMonitoringSummary(options?: { enabled?: boolean }) {
  return useQuery<MonitoringSummary, ApiError>({
    queryKey: queryKeys.monitoring.summary(),
    queryFn: () => monitoringService.summary(),
    enabled: options?.enabled ?? true,
    refetchInterval: MONITORING_POLL_MS,
    retry: false,
  })
}

/** Servers needing attention, already ordered worst-first by the API. */
export function useServersNeedingAttention(options?: { enabled?: boolean }) {
  return useQuery<ServerSummary[], ApiError>({
    queryKey: queryKeys.monitoring.attention(),
    queryFn: () => monitoringService.attention(),
    enabled: options?.enabled ?? true,
    refetchInterval: MONITORING_POLL_MS,
    retry: false,
  })
}
