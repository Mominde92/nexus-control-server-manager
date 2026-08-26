import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createCrudHooks } from '@/hooks/use-crud-queries'
import { queryKeys } from '@/lib/query-keys'
import { serverService } from '@/services/server.service'
import type { ApiError } from '@/types/api'
import type {
  AgentCredential,
  AgentCredentialPayload,
  AgentInstructions,
  IssuedAgentToken,
  LatestMetric,
  MetricRange,
  MetricSeries,
  Server,
  ServerPayload,
} from '@/types/models'

const hooks = createCrudHooks<Server, ServerPayload>('servers', serverService)

/**
 * List/detail/CRUD come straight from the shared factory, so a server created
 * through the API appears on the next fetch with no code change here.
 */
export const useServers = hooks.useList
export const useServer = hooks.useDetail
export const useCreateServer = hooks.useCreate
export const useUpdateServer = hooks.useUpdate
export const useDeleteServer = hooks.useDelete
export const useRestoreServer = hooks.useRestore

/** Trigger the CSV export for the current filter set. */
export const exportServers = serverService.exportCsv

/** Invalidate every server-derived view plus the monitoring aggregates. */
function useInvalidateServer() {
  const client = useQueryClient()

  return (id?: number | string) => {
    void client.invalidateQueries({ queryKey: queryKeys.servers.lists() })
    void client.invalidateQueries({ queryKey: queryKeys.monitoring.all })

    if (id !== undefined) {
      void client.invalidateQueries({ queryKey: queryKeys.servers.detail(id) })
    }
  }
}

function reportError(error: ApiError, fallback: string): void {
  toast.error(error?.message ?? fallback)
}

export function useEnableMonitoring() {
  const invalidate = useInvalidateServer()

  return useMutation<Server, ApiError, number | string>({
    mutationFn: async (id) => {
      let message = ''
      const server = await serverService.enableMonitoring(id, {
        onMessage: (value) => {
          message = value
        },
      })
      toast.success(message.trim() || 'Monitoring enabled.')

      return server
    },
    onSuccess: (_data, id) => {
      invalidate(id)
    },
    onError: (error) => {
      reportError(error, 'Could not enable monitoring.')
    },
  })
}

export function useDisableMonitoring() {
  const invalidate = useInvalidateServer()

  return useMutation<Server, ApiError, number | string>({
    mutationFn: async (id) => {
      let message = ''
      const server = await serverService.disableMonitoring(id, {
        onMessage: (value) => {
          message = value
        },
      })
      toast.success(message.trim() || 'Monitoring disabled.')

      return server
    },
    onSuccess: (_data, id) => {
      invalidate(id)
    },
    onError: (error) => {
      reportError(error, 'Could not disable monitoring.')
    },
  })
}

/* -------------------------------------------------------------------------- */
/* Agent credentials                                                          */
/* -------------------------------------------------------------------------- */

export function useAgentCredentials(
  id: number | string | undefined,
  options?: { enabled?: boolean },
) {
  return useQuery<AgentCredential[], ApiError>({
    queryKey: queryKeys.servers.agentCredentials(id ?? 'none'),
    queryFn: () => serverService.credentials(id!),
    enabled: (options?.enabled ?? true) && id !== undefined && id !== 'new',
  })
}

/** Install steps; `null` when the endpoint is not deployed yet (404). */
export function useAgentInstructions(
  id: number | string | undefined,
  options?: { enabled?: boolean },
) {
  return useQuery<AgentInstructions | null, ApiError>({
    queryKey: queryKeys.servers.agentInstructions(id ?? 'none'),
    queryFn: async () => {
      try {
        return await serverService.instructions(id!)
      } catch (cause) {
        if ((cause as ApiError | undefined)?.status === 404) {
          return null
        }

        throw cause
      }
    },
    enabled: (options?.enabled ?? true) && id !== undefined && id !== 'new',
    retry: false,
  })
}

/** The credential list is invalidated, but the plaintext token is never cached. */
function useInvalidateCredentials(id: number | string | undefined) {
  const client = useQueryClient()

  return () => {
    void client.invalidateQueries({
      queryKey: queryKeys.servers.agentCredentials(id ?? 'none'),
    })

    if (id !== undefined) {
      void client.invalidateQueries({ queryKey: queryKeys.servers.detail(id) })
    }
  }
}

export function useIssueCredential(id: number | string | undefined) {
  const invalidate = useInvalidateCredentials(id)

  return useMutation<IssuedAgentToken, ApiError, AgentCredentialPayload | void>({
    mutationFn: async (payload) => {
      let message = ''
      const issued = await serverService.issueCredential(
        id!,
        payload ?? undefined,
        {
          onMessage: (value) => {
            message = value
          },
        },
      )
      toast.success(message.trim() || 'Agent token issued.')

      return issued
    },
    onSuccess: () => {
      invalidate()
    },
    onError: (error) => {
      reportError(error, 'Could not issue an agent token.')
    },
  })
}

export function useRotateCredential(id: number | string | undefined) {
  const invalidate = useInvalidateCredentials(id)

  return useMutation<IssuedAgentToken, ApiError, void>({
    mutationFn: async () => {
      let message = ''
      const issued = await serverService.rotateCredential(id!, {
        onMessage: (value) => {
          message = value
        },
      })
      toast.success(message.trim() || 'Agent token rotated.')

      return issued
    },
    onSuccess: () => {
      invalidate()
    },
    onError: (error) => {
      reportError(error, 'Could not rotate the agent token.')
    },
  })
}

export function useRevokeCredential(id: number | string | undefined) {
  const invalidate = useInvalidateCredentials(id)

  return useMutation<null, ApiError, number | string>({
    mutationFn: async (credentialId) => {
      let message = ''
      await serverService.revokeCredential(id!, credentialId, {
        onMessage: (value) => {
          message = value
        },
      })
      toast.success(message.trim() || 'Agent token revoked.')

      return null
    },
    onSuccess: () => {
      invalidate()
    },
    onError: (error) => {
      reportError(error, 'Could not revoke the agent token.')
    },
  })
}

/* -------------------------------------------------------------------------- */
/* Metrics                                                                    */
/* -------------------------------------------------------------------------- */

/**
 * A single metric type over a single range for one server. Deliberately keyed on
 * `(id, type, range)` so switching either only fetches what is on screen.
 */
export function useServerMetrics(
  id: number | string | undefined,
  type: string | undefined,
  range: MetricRange | string,
  options?: { enabled?: boolean },
) {
  return useQuery<MetricSeries, ApiError>({
    queryKey: queryKeys.servers.metrics(id ?? 'none', type ?? 'none', range),
    queryFn: () => serverService.metrics(id!, { type: type!, range }),
    enabled:
      (options?.enabled ?? true) &&
      id !== undefined &&
      id !== 'new' &&
      Boolean(type),
  })
}

export function useLatestMetrics(
  id: number | string | undefined,
  options?: { enabled?: boolean },
) {
  return useQuery<LatestMetric[], ApiError>({
    queryKey: queryKeys.servers.latestMetrics(id ?? 'none'),
    queryFn: () => serverService.latestMetrics(id!),
    enabled: (options?.enabled ?? true) && id !== undefined && id !== 'new',
  })
}
