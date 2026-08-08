import { useQuery } from '@tanstack/react-query'

import { queryKeys } from '@/lib/query-keys'
import { activityLogService } from '@/services/activity-log.service'
import type { ApiError, ListParams, PaginatedResult } from '@/types/api'
import type { ActivityLog } from '@/types/models'

export function useActivityLogs(params?: ListParams) {
  return useQuery<PaginatedResult<ActivityLog>, ApiError>({
    queryKey: queryKeys.activityLogs.list(params),
    queryFn: () => activityLogService.list(params),
  })
}

export function useActivityLog(id: number | string | undefined) {
  return useQuery<ActivityLog, ApiError>({
    queryKey: queryKeys.activityLogs.detail(id ?? 'none'),
    queryFn: () => activityLogService.get(id!),
    enabled: id !== undefined && id !== null && id !== '',
  })
}

/** Trigger the CSV export for the current filter set. */
export const exportActivityLogs = activityLogService.exportCsv
