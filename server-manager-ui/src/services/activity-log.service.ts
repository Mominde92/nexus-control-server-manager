import apiClient, { unwrap, unwrapList } from '@/lib/api-client'
import { downloadFile, timestampedFilename } from '@/lib/download'
import type { ApiEnvelope, ListParams, PaginatedResult } from '@/types/api'
import type { ActivityLog } from '@/types/models'

/**
 * Activity logs are read-only, so this is not built from the CRUD factory —
 * exposing create/update/delete here would be a lie.
 */
export const activityLogService = {
  resource: 'activity-logs',

  async list(params?: ListParams): Promise<PaginatedResult<ActivityLog>> {
    return unwrapList(
      await apiClient.get<ApiEnvelope<ActivityLog[]>>('/activity-logs', { params }),
    )
  },

  async get(id: number | string): Promise<ActivityLog> {
    return unwrap(
      await apiClient.get<ApiEnvelope<ActivityLog>>(`/activity-logs/${id}`),
    )
  },

  async exportCsv(params?: ListParams): Promise<void> {
    await downloadFile(
      '/activity-logs/export',
      params,
      timestampedFilename('activity-logs'),
    )
  },
}

export default activityLogService
