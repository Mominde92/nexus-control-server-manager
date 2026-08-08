import * as React from 'react'
import { useQueryClient } from '@tanstack/react-query'

import { ensureCsrfToken, setUnauthorizedHandler } from '@/lib/api-client'
import { queryKeys } from '@/lib/query-keys'
import { authService } from '@/services/auth.service'
import type { AuthUser } from '@/types/models'

export interface AuthContextValue {
  user: AuthUser | null
  /** True only while the initial session bootstrap is in flight. */
  isLoading: boolean
  isAuthenticated: boolean
  login: (email: string, password: string, remember?: boolean) => Promise<AuthUser>
  logout: () => Promise<void>
  refresh: () => Promise<AuthUser | null>
  can: (permission: string | string[]) => boolean
  hasRole: (role: string | string[]) => boolean
  isAdmin: boolean
}

export const AuthContext = React.createContext<AuthContextValue | null>(null)

const ADMIN_ROLES = ['admin', 'super-admin', 'super_admin']

/**
 * Session provider for Sanctum SPA cookie auth.
 *
 * There is no token in JavaScript: the session cookie is httpOnly, so we
 * discover who we are by asking `GET /auth/me` once on mount. That also means a
 * hard refresh silently restores the session.
 */
export function AuthProvider({ children }: { children: React.ReactNode }) {
  const queryClient = useQueryClient()
  const [user, setUser] = React.useState<AuthUser | null>(null)
  const [isLoading, setIsLoading] = React.useState(true)

  const refresh = React.useCallback(async (): Promise<AuthUser | null> => {
    try {
      const fresh = await authService.me()
      setUser(fresh)

      return fresh
    } catch {
      setUser(null)

      return null
    }
  }, [])

  // Bootstrap once. A 401 here is the expected "not signed in" answer.
  React.useEffect(() => {
    let cancelled = false

    void (async () => {
      try {
        const fresh = await authService.me()

        if (!cancelled) {
          setUser(fresh)
        }
      } catch {
        if (!cancelled) {
          setUser(null)
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    })()

    return () => {
      cancelled = true
    }
  }, [])

  // Let the axios interceptor drop our state when the API reports 401.
  React.useEffect(() => {
    setUnauthorizedHandler(() => {
      setUser(null)
      queryClient.clear()
    })

    return () => {
      setUnauthorizedHandler(null)
    }
  }, [queryClient])

  const login = React.useCallback(
    async (email: string, password: string, remember = false) => {
      await ensureCsrfToken()

      const authenticated = await authService.login({ email, password, remember })
      setUser(authenticated)
      queryClient.setQueryData(queryKeys.auth.me(), authenticated)
      await queryClient.invalidateQueries()

      return authenticated
    },
    [queryClient],
  )

  const logout = React.useCallback(async () => {
    try {
      await authService.logout()
    } finally {
      setUser(null)
      // Wipe everything so one user's records never surface in the next session.
      queryClient.clear()
    }
  }, [queryClient])

  const can = React.useCallback(
    (permission: string | string[]) => {
      if (!user) {
        return false
      }

      const granted = user.permissions ?? []
      const wanted = Array.isArray(permission) ? permission : [permission]

      return wanted.some((item) => granted.includes(item))
    },
    [user],
  )

  const hasRole = React.useCallback(
    (role: string | string[]) => {
      if (!user?.role) {
        return false
      }

      const wanted = Array.isArray(role) ? role : [role]

      return wanted.includes(user.role.value)
    },
    [user],
  )

  const value = React.useMemo<AuthContextValue>(
    () => ({
      user,
      isLoading,
      isAuthenticated: user !== null,
      login,
      logout,
      refresh,
      can,
      hasRole,
      isAdmin: user?.role ? ADMIN_ROLES.includes(user.role.value) : false,
    }),
    [user, isLoading, login, logout, refresh, can, hasRole],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
