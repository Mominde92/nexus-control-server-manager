import apiClient, { unwrap } from '@/lib/api-client'
import { createCrudService } from '@/services/crud.service'
import type { ApiEnvelope } from '@/types/api'
import type { Credentials, Domain, DomainPayload } from '@/types/models'

const crud = createCrudService<Domain, DomainPayload>('domains')

export const domainService = {
  ...crud,

  /** Decrypted secrets — every call is written to the audit log server-side. */
  async credentials(id: number | string): Promise<Credentials> {
    return unwrap(
      await apiClient.get<ApiEnvelope<Credentials>>(`/domains/${id}/credentials`),
    )
  },
}

export default domainService
