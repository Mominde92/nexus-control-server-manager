import apiClient, { unwrap } from '@/lib/api-client'
import type { ApiEnvelope } from '@/types/api'
import type { MetaOptions } from '@/types/models'

export const metaService = {
  /** Every select option in the app, in one request. */
  async options(): Promise<MetaOptions> {
    return unwrap(await apiClient.get<ApiEnvelope<MetaOptions>>('/meta/options'))
  },
}

export default metaService
