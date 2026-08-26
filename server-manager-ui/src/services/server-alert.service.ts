import apiClient, { unwrap } from '@/lib/api-client'
import { createCrudService, type RequestOptions } from '@/services/crud.service'
import type { ApiEnvelope } from '@/types/api'
import type { ServerAlert } from '@/types/models'

/** Alerts are read-only apart from acknowledge/resolve. */
const crud = createCrudService<ServerAlert, never>('server-alerts')

function emit(options: RequestOptions | undefined, message: string): void {
  options?.onMessage?.(message)
}

export const serverAlertService = {
  resource: crud.resource,
  list: crud.list,
  get: crud.get,

  async acknowledge(
    id: number | string,
    options?: RequestOptions,
  ): Promise<ServerAlert> {
    const response = await apiClient.post<ApiEnvelope<ServerAlert>>(
      `/server-alerts/${id}/acknowledge`,
    )
    emit(options, response.data.message)

    return unwrap(response)
  },

  async resolve(
    id: number | string,
    options?: RequestOptions,
  ): Promise<ServerAlert> {
    const response = await apiClient.post<ApiEnvelope<ServerAlert>>(
      `/server-alerts/${id}/resolve`,
    )
    emit(options, response.data.message)

    return unwrap(response)
  },
}

export default serverAlertService
