import apiClient, { unwrap } from '@/lib/api-client'
import { createCrudService } from '@/services/crud.service'
import type { ApiEnvelope } from '@/types/api'
import type { User, UserPayload } from '@/types/models'

const crud = createCrudService<User, UserPayload>('users')

export const userService = {
  ...crud,

  /** Activate / suspend a user without touching the rest of the record. */
  async changeStatus(id: number | string, status: string): Promise<User> {
    return unwrap(
      await apiClient.patch<ApiEnvelope<User>>(`/users/${id}/status`, { status }),
    )
  },
}

export default userService
