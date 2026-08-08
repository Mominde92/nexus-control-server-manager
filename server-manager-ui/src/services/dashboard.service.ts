import apiClient, { unwrap } from '@/lib/api-client'
import type { ApiEnvelope, ListParams } from '@/types/api'
import type { ActivityLog, DashboardStats, ExpiringItem } from '@/types/models'

export const dashboardService = {
  async stats(): Promise<DashboardStats> {
    return unwrap(
      await apiClient.get<ApiEnvelope<DashboardStats>>('/dashboard/stats'),
    )
  },

  async expiringHostings(params?: ListParams): Promise<ExpiringItem[]> {
    return unwrap(
      await apiClient.get<ApiEnvelope<ExpiringItem[]>>(
        '/dashboard/expiring-hostings',
        { params },
      ),
    )
  },

  async expiringDomains(params?: ListParams): Promise<ExpiringItem[]> {
    return unwrap(
      await apiClient.get<ApiEnvelope<ExpiringItem[]>>(
        '/dashboard/expiring-domains',
        { params },
      ),
    )
  },

  async recentActivities(params?: ListParams): Promise<ActivityLog[]> {
    return unwrap(
      await apiClient.get<ApiEnvelope<ActivityLog[]>>(
        '/dashboard/recent-activities',
        { params },
      ),
    )
  },
}

export default dashboardService
