import apiClient, { unwrap } from '@/lib/api-client'
import { createCrudService } from '@/services/crud.service'
import type { ApiEnvelope } from '@/types/api'
import type { Credentials, Hosting, HostingPayload } from '@/types/models'

const crud = createCrudService<Hosting, HostingPayload>('hostings')

export const hostingService = {
  ...crud,

  /** Decrypted secrets — every call is written to the audit log server-side. */
  async credentials(id: number | string): Promise<Credentials> {
    return unwrap(
      await apiClient.get<ApiEnvelope<Credentials>>(`/hostings/${id}/credentials`),
    )
  },
}

export default hostingService
