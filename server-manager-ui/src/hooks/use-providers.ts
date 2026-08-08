import { createCrudHooks } from '@/hooks/use-crud-queries'
import { providerService } from '@/services/provider.service'
import type { Provider, ProviderPayload } from '@/types/models'

const hooks = createCrudHooks<Provider, ProviderPayload>(
  'providers',
  providerService,
)

export const useProviders = hooks.useList
export const useProvider = hooks.useDetail
export const useCreateProvider = hooks.useCreate
export const useUpdateProvider = hooks.useUpdate
export const useDeleteProvider = hooks.useDelete
export const useRestoreProvider = hooks.useRestore

/** Trigger the CSV export for the current filter set. */
export const exportProviders = providerService.exportCsv
