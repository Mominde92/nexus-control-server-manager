import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createCrudHooks } from '@/hooks/use-crud-queries'
import { queryKeys } from '@/lib/query-keys'
import { userService } from '@/services/user.service'
import type { ApiError } from '@/types/api'
import type { User, UserPayload } from '@/types/models'

const hooks = createCrudHooks<User, UserPayload>('users', userService)

export const useUsers = hooks.useList
export const useUser = hooks.useDetail
export const useCreateUser = hooks.useCreate
export const useUpdateUser = hooks.useUpdate
export const useDeleteUser = hooks.useDelete
export const useRestoreUser = hooks.useRestore

export interface ChangeUserStatusVariables {
  id: number | string
  status: string
}

/** Activate / suspend a user without submitting the whole edit form. */
export function useChangeUserStatus() {
  const client = useQueryClient()

  return useMutation<User, ApiError, ChangeUserStatusVariables>({
    mutationFn: ({ id, status }) => userService.changeStatus(id, status),
    onSuccess: (_data, variables) => {
      void client.invalidateQueries({ queryKey: queryKeys.users.lists() })
      void client.invalidateQueries({
        queryKey: queryKeys.users.detail(variables.id),
      })
      toast.success('User status updated.')
    },
    onError: (error) => {
      toast.error(error?.message ?? 'Could not update the user status.')
    },
  })
}

/** Trigger the CSV export for the current filter set. */
export const exportUsers = userService.exportCsv
