import { createCrudService } from '@/services/crud.service'
import type { Client, ClientPayload } from '@/types/models'

export const clientService = createCrudService<Client, ClientPayload>('clients')

export default clientService
