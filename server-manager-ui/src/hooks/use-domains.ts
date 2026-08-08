import { useQuery } from '@tanstack/react-query'

import { createCrudHooks } from '@/hooks/use-crud-queries'
import { queryKeys } from '@/lib/query-keys'
import { domainService } from '@/services/domain.service'
import type { ApiError } from '@/types/api'
import type { Credentials, Domain, DomainPayload } from '@/types/models'

const hooks = createCrudHooks<Domain, DomainPayload>('domains', domainService)

export const useDomains = hooks.useList
export const useDomain = hooks.useDetail
export const useCreateDomain = hooks.useCreate
export const useUpdateDomain = hooks.useUpdate
export const useDeleteDomain = hooks.useDelete
export const useRestoreDomain = hooks.useRestore

/**
 * Decrypted registrar secrets. Manual by design (`enabled: false`) — the reveal
 * is audited server-side, so it only fires through the returned `refetch`.
 */
export function useDomainCredentials(id: number | string | undefined) {
  return useQuery<Credentials, ApiError>({
    queryKey: queryKeys.domains.credentials(id ?? 'none'),
    queryFn: () => domainService.credentials(id!),
    enabled: false,
    gcTime: 0,
    staleTime: 0,
  })
}

/** Alias matching the shared component contract. */
export const useCredentials = useDomainCredentials

/** Trigger the CSV export for the current filter set. */
export const exportDomains = domainService.exportCsv
