import { useQuery } from '@tanstack/react-query'

import { createCrudHooks } from '@/hooks/use-crud-queries'
import { queryKeys } from '@/lib/query-keys'
import { hostingService } from '@/services/hosting.service'
import type { ApiError } from '@/types/api'
import type { Credentials, Hosting, HostingPayload } from '@/types/models'

const hooks = createCrudHooks<Hosting, HostingPayload>('hostings', hostingService)

export const useHostings = hooks.useList
export const useHosting = hooks.useDetail
export const useCreateHosting = hooks.useCreate
export const useUpdateHosting = hooks.useUpdate
export const useDeleteHosting = hooks.useDelete
export const useRestoreHosting = hooks.useRestore

/**
 * Decrypted hosting secrets. Manual by design (`enabled: false`) — the reveal is
 * audited server-side, so it must only ever fire on an explicit user action via
 * the returned `refetch`.
 */
export function useHostingCredentials(id: number | string | undefined) {
  return useQuery<Credentials, ApiError>({
    queryKey: queryKeys.hostings.credentials(id ?? 'none'),
    queryFn: () => hostingService.credentials(id!),
    enabled: false,
    gcTime: 0,
    staleTime: 0,
  })
}

/** Alias matching the shared component contract. */
export const useCredentials = useHostingCredentials

/** Trigger the CSV export for the current filter set. */
export const exportHostings = hostingService.exportCsv
