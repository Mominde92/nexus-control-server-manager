import * as React from 'react'
import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'
import { toast } from 'sonner'

import { resourceQueryKeys } from '@/lib/query-keys'
import type { CrudService } from '@/services/crud.service'
import type { ApiError, ListParams, PaginatedResult } from '@/types/api'

/** Payload shape for the update mutation — id plus the form body. */
export interface UpdateVariables<TPayload> {
  id: number | string
  payload: TPayload
}

export interface CrudHooks<T, TPayload> {
  useList: (
    params?: ListParams,
    options?: { enabled?: boolean },
  ) => UseQueryResult<PaginatedResult<T>, ApiError>
  useDetail: (id: number | string | undefined) => UseQueryResult<T, ApiError>
  useCreate: () => UseMutationResult<T, ApiError, TPayload>
  useUpdate: () => UseMutationResult<T, ApiError, UpdateVariables<TPayload>>
  useDelete: () => UseMutationResult<null, ApiError, number | string>
  useRestore: () => UseMutationResult<T, ApiError, number | string>
}

/**
 * Build the six standard React Query hooks for a CRUD resource.
 *
 * Mutations invalidate the resource's list/detail keys plus the dashboard
 * counters through the shared `queryKeys` factories — so an invalidation can
 * never drift from the key a hook actually used — and surface the API's own
 * `message` through sonner.
 */
export function createCrudHooks<T, TPayload>(
  resource: string,
  service: CrudService<T, TPayload>,
): CrudHooks<T, TPayload> {
  const keys = resourceQueryKeys(resource)

  /** One invalidation strategy shared by all four mutations. */
  function useInvalidateResource() {
    const client = useQueryClient()

    return React.useCallback(
      (id?: number | string) => {
        void client.invalidateQueries({ queryKey: keys.lists() })
        void client.invalidateQueries({ queryKey: ['dashboard'] })

        if (id !== undefined) {
          void client.invalidateQueries({ queryKey: keys.detail(id) })
        }
      },
      [client],
    )
  }

  /** Run a mutating service call and toast whatever message the API returned. */
  async function withToast<R>(
    fallback: string,
    run: (options: { onMessage: (message: string) => void }) => Promise<R>,
  ): Promise<R> {
    let message = ''
    const result = await run({
      onMessage: (value) => {
        message = value
      },
    })

    toast.success(message.trim().length > 0 ? message : fallback)

    return result
  }

  function reportError(error: ApiError, fallback: string): void {
    toast.error(error?.message ?? fallback)
  }

  return {
    useList(params, options) {
      return useQuery<PaginatedResult<T>, ApiError>({
        queryKey: keys.list(params),
        queryFn: () => service.list(params),
        enabled: options?.enabled ?? true,
      })
    },

    useDetail(id) {
      const enabled =
        id !== undefined && id !== null && id !== '' && id !== 'new'

      return useQuery<T, ApiError>({
        queryKey: keys.detail(id ?? 'new'),
        queryFn: () => service.get(id!),
        enabled,
      })
    },

    useCreate() {
      const invalidate = useInvalidateResource()

      return useMutation<T, ApiError, TPayload>({
        mutationFn: (payload) =>
          withToast('Record created successfully.', (options) =>
            service.create(payload, options),
          ),
        onSuccess: () => {
          invalidate()
        },
        onError: (error) => {
          reportError(error, 'Could not create the record.')
        },
      })
    },

    useUpdate() {
      const invalidate = useInvalidateResource()

      return useMutation<T, ApiError, UpdateVariables<TPayload>>({
        mutationFn: ({ id, payload }) =>
          withToast('Changes saved successfully.', (options) =>
            service.update(id, payload, options),
          ),
        onSuccess: (_data, variables) => {
          invalidate(variables.id)
        },
        onError: (error) => {
          reportError(error, 'Could not save your changes.')
        },
      })
    },

    useDelete() {
      const invalidate = useInvalidateResource()

      return useMutation<null, ApiError, number | string>({
        mutationFn: (id) =>
          withToast('Record deleted successfully.', (options) =>
            service.remove(id, options),
          ),
        onSuccess: (_data, id) => {
          invalidate(id)
        },
        onError: (error) => {
          reportError(error, 'Could not delete the record.')
        },
      })
    },

    useRestore() {
      const invalidate = useInvalidateResource()

      return useMutation<T, ApiError, number | string>({
        mutationFn: (id) =>
          withToast('Record restored successfully.', (options) =>
            service.restore(id, options),
          ),
        onSuccess: (_data, id) => {
          invalidate(id)
        },
        onError: (error) => {
          reportError(error, 'Could not restore the record.')
        },
      })
    },
  }
}

export default createCrudHooks
