import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { queryKeys } from '@/lib/query-keys'
import { serverAlertService } from '@/services/server-alert.service'
import type { ApiError, ListParams, PaginatedResult } from '@/types/api'
import type { ServerAlert } from '@/types/models'

/**
 * Paginated alerts. Filters (`severity`, `status`, `type`, `server_id`) are just
 * query params, so the same hook serves the global list and a server's tab.
 */
export function useServerAlerts(
  params?: ListParams,
  options?: { enabled?: boolean; refetchInterval?: number },
) {
  return useQuery<PaginatedResult<ServerAlert>, ApiError>({
    queryKey: queryKeys.serverAlerts.list(params),
    queryFn: () => serverAlertService.list(params),
    enabled: options?.enabled ?? true,
    refetchInterval: options?.refetchInterval ?? 30_000,
  })
}

export function useServerAlert(id: number | string | undefined) {
  return useQuery<ServerAlert, ApiError>({
    queryKey: queryKeys.serverAlerts.detail(id ?? 'none'),
    queryFn: () => serverAlertService.get(id!),
    enabled: id !== undefined && id !== 'new',
  })
}

/** Alert mutations also refresh the fleet aggregates and the server records. */
function useInvalidateAlerts() {
  const client = useQueryClient()

  return (id?: number | string) => {
    void client.invalidateQueries({ queryKey: queryKeys.serverAlerts.lists() })
    void client.invalidateQueries({ queryKey: queryKeys.monitoring.all })
    void client.invalidateQueries({ queryKey: queryKeys.servers.lists() })

    if (id !== undefined) {
      void client.invalidateQueries({
        queryKey: queryKeys.serverAlerts.detail(id),
      })
    }
  }
}

export function useAcknowledgeAlert() {
  const invalidate = useInvalidateAlerts()

  return useMutation<ServerAlert, ApiError, number | string>({
    mutationFn: async (id) => {
      let message = ''
      const alert = await serverAlertService.acknowledge(id, {
        onMessage: (value) => {
          message = value
        },
      })
      toast.success(message.trim() || 'Alert acknowledged.')

      return alert
    },
    onSuccess: (_data, id) => {
      invalidate(id)
    },
    onError: (error) => {
      toast.error(error?.message ?? 'Could not acknowledge the alert.')
    },
  })
}

export function useResolveAlert() {
  const invalidate = useInvalidateAlerts()

  return useMutation<ServerAlert, ApiError, number | string>({
    mutationFn: async (id) => {
      let message = ''
      const alert = await serverAlertService.resolve(id, {
        onMessage: (value) => {
          message = value
        },
      })
      toast.success(message.trim() || 'Alert resolved.')

      return alert
    },
    onSuccess: (_data, id) => {
      invalidate(id)
    },
    onError: (error) => {
      toast.error(error?.message ?? 'Could not resolve the alert.')
    },
  })
}
