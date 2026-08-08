import { createCrudHooks } from '@/hooks/use-crud-queries'
import { clientService } from '@/services/client.service'
import type { Client, ClientPayload } from '@/types/models'

const hooks = createCrudHooks<Client, ClientPayload>('clients', clientService)

export const useClients = hooks.useList
export const useClient = hooks.useDetail
export const useCreateClient = hooks.useCreate
export const useUpdateClient = hooks.useUpdate
export const useDeleteClient = hooks.useDelete
export const useRestoreClient = hooks.useRestore

/** Trigger the CSV export for the current filter set. */
export const exportClients = clientService.exportCsv
