import apiClient, { ensureCsrfToken, unwrap } from '@/lib/api-client'
import type { ApiEnvelope } from '@/types/api'
import type { AuthUser } from '@/types/models'

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface ProfilePayload {
  name: string
  email: string
  phone?: string | null
  job_title?: string | null
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export const authService = {
  /** Sanctum requires the CSRF cookie before the first stateful POST. */
  async login(payload: LoginPayload): Promise<AuthUser> {
    await ensureCsrfToken()

    return unwrap(
      await apiClient.post<ApiEnvelope<AuthUser>>('/auth/login', payload),
    )
  },

  async logout(): Promise<void> {
    await apiClient.post<ApiEnvelope<null>>('/auth/logout')
  },

  async me(): Promise<AuthUser> {
    return unwrap(await apiClient.get<ApiEnvelope<AuthUser>>('/auth/me'))
  },

  async updateProfile(payload: ProfilePayload): Promise<AuthUser> {
    return unwrap(
      await apiClient.put<ApiEnvelope<AuthUser>>('/auth/profile', payload),
    )
  },

  async changePassword(payload: ChangePasswordPayload): Promise<null> {
    await apiClient.put<ApiEnvelope<null>>('/auth/password', payload)

    return null
  },

  async forgotPassword(email: string): Promise<string> {
    await ensureCsrfToken()

    const response = await apiClient.post<ApiEnvelope<null>>(
      '/auth/forgot-password',
      { email },
    )

    return response.data.message
  },

  async resetPassword(payload: ResetPasswordPayload): Promise<string> {
    await ensureCsrfToken()

    const response = await apiClient.post<ApiEnvelope<null>>(
      '/auth/reset-password',
      payload,
    )

    return response.data.message
  },
}

export default authService
