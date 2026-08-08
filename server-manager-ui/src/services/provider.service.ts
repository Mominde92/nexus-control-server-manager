import { createCrudService } from '@/services/crud.service'
import type { Provider, ProviderPayload } from '@/types/models'

export const providerService = createCrudService<Provider, ProviderPayload>(
  'providers',
)

export default providerService
