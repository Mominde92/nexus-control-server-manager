import apiClient, { unwrap, unwrapList } from '@/lib/api-client'
import { downloadFile, timestampedFilename } from '@/lib/download'
import type { ApiEnvelope, ListParams, PaginatedResult } from '@/types/api'

/**
 * Optional per-call hook so a caller can read the envelope's `message` without
 * the service having to return a wrapper object — mutations still resolve to
 * the plain record, which is what callers actually want to navigate with.
 */
export interface RequestOptions {
  onMessage?: (message: string) => void
}

function emit(options: RequestOptions | undefined, message: string): void {
  options?.onMessage?.(message)
}

/** The uniform CRUD surface every resource shares. */
export interface CrudService<T, TPayload> {
  resource: string
  list: (params?: ListParams) => Promise<PaginatedResult<T>>
  get: (id: number | string) => Promise<T>
  create: (payload: TPayload, options?: RequestOptions) => Promise<T>
  update: (
    id: number | string,
    payload: TPayload,
    options?: RequestOptions,
  ) => Promise<T>
  remove: (id: number | string, options?: RequestOptions) => Promise<null>
  restore: (id: number | string, options?: RequestOptions) => Promise<T>
  exportCsv: (params?: ListParams) => Promise<void>
}

/**
 * Build a fully typed CRUD service for a REST resource.
 *
 * Every module's endpoints are identical apart from the path segment, so this
 * factory is the single implementation — resource-specific extras (credentials,
 * status changes) are spread on top of the returned object.
 */
export function createCrudService<T, TPayload>(
  resource: string,
): CrudService<T, TPayload> {
  const base = `/${resource}`

  return {
    resource,

    async list(params) {
      return unwrapList(await apiClient.get<ApiEnvelope<T[]>>(base, { params }))
    },

    async get(id) {
      return unwrap(await apiClient.get<ApiEnvelope<T>>(`${base}/${id}`))
    },

    async create(payload, options) {
      const response = await apiClient.post<ApiEnvelope<T>>(base, payload)
      emit(options, response.data.message)

      return unwrap(response)
    },

    async update(id, payload, options) {
      const response = await apiClient.put<ApiEnvelope<T>>(
        `${base}/${id}`,
        payload,
      )
      emit(options, response.data.message)

      return unwrap(response)
    },

    async remove(id, options) {
      const response = await apiClient.delete<ApiEnvelope<null>>(`${base}/${id}`)
      emit(options, response.data.message)

      return null
    },

    async restore(id, options) {
      const response = await apiClient.post<ApiEnvelope<T>>(
        `${base}/${id}/restore`,
      )
      emit(options, response.data.message)

      return unwrap(response)
    },

    async exportCsv(params) {
      await downloadFile(`${base}/export`, params, timestampedFilename(resource))
    },
  }
}

export default createCrudService
