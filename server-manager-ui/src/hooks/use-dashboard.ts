import { useQuery } from '@tanstack/react-query'

import { queryKeys } from '@/lib/query-keys'
import { dashboardService } from '@/services/dashboard.service'
import type { ApiError, ListParams } from '@/types/api'
import type { ActivityLog, DashboardStats, ExpiringItem } from '@/types/models'

export function useDashboardStats() {
  return useQuery<DashboardStats, ApiError>({
    queryKey: queryKeys.dashboard.stats(),
    queryFn: () => dashboardService.stats(),
  })
}

export function useExpiringHostings(params?: ListParams) {
  return useQuery<ExpiringItem[], ApiError>({
    queryKey: queryKeys.dashboard.expiringHostings(params),
    queryFn: () => dashboardService.expiringHostings(params),
  })
}

export function useExpiringDomains(params?: ListParams) {
  return useQuery<ExpiringItem[], ApiError>({
    queryKey: queryKeys.dashboard.expiringDomains(params),
    queryFn: () => dashboardService.expiringDomains(params),
  })
}

export function useRecentActivities(params?: ListParams) {
  return useQuery<ActivityLog[], ApiError>({
    queryKey: queryKeys.dashboard.recentActivities(params),
    queryFn: () => dashboardService.recentActivities(params),
  })
}
