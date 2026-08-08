import axios, {
  AxiosError,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from 'axios'

import type { ApiEnvelope, ApiError } from '@/types/api'

/** Origin of the Laravel app (NOT including `/api`). */
export const API_ORIGIN: string =
  (import.meta.env.VITE_API_URL as string | undefined) ?? window.location.origin

/** Routes where a 401 must NOT trigger a redirect (we are already there). */
const AUTH_ROUTES = ['/login', '/forgot-password', '/reset-password']

/**
 * Configured axios instance for the whole app.
 *
 * Sanctum SPA cookie mode: the session lives in an httpOnly cookie, so
 * `withCredentials` is mandatory and no token is ever stored in JS.
 */
const apiClient = axios.create({
  baseURL: `${API_ORIGIN}/api`,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

/* -------------------------------------------------------------------------- */
/* CSRF cookie                                                                */
/* -------------------------------------------------------------------------- */

let csrfPromise: Promise<void> | null = null

/**
 * Fetch the Sanctum CSRF cookie. Must run before the first stateful request.
 *
 * The in-flight promise is memoised so concurrent callers share one round trip;
 * it is reset on 419 so a stale token can be refreshed.
 */
export function ensureCsrfToken(): Promise<void> {
  if (!csrfPromise) {
    csrfPromise = axios
      .get(`${API_ORIGIN}/sanctum/csrf-cookie`, {
        withCredentials: true,
        headers: { Accept: 'application/json' },
      })
      .then(() => undefined)
      .catch((error: unknown) => {
        // Allow a later retry rather than caching the failure forever.
        csrfPromise = null
        throw error
      })
  }

  return csrfPromise
}

/** Forget the memoised CSRF cookie request (used after a 419). */
export function resetCsrfToken(): void {
  csrfPromise = null
}

/* -------------------------------------------------------------------------- */
/* Auth-state clearing hook                                                   */
/* -------------------------------------------------------------------------- */

type UnauthorizedHandler = () => void

let onUnauthorized: UnauthorizedHandler | null = null

/**
 * Register a callback invoked when the API reports 401. The auth context uses
 * this to drop the current user and the query cache without creating an import
 * cycle between the client and the React tree.
 */
export function setUnauthorizedHandler(handler: UnauthorizedHandler | null): void {
  onUnauthorized = handler
}

/* -------------------------------------------------------------------------- */
/* Error normalisation                                                        */
/* -------------------------------------------------------------------------- */

function messageFromStatus(status: number, fallback?: string): string {
  if (fallback) {
    return fallback
  }

  switch (status) {
    case 401:
      return 'Your session has expired. Please sign in again.'
    case 403:
      return 'You do not have permission to perform this action.'
    case 404:
      return 'The requested record could not be found.'
    case 419:
      return 'Your session expired. Please try again.'
    case 422:
      return 'Please review the highlighted fields.'
    case 429:
      return 'Too many requests. Please slow down and try again shortly.'
    default:
      return status >= 500
        ? 'Something went wrong on the server. Please try again.'
        : 'The request could not be completed.'
  }
}

/** Config flag used to guarantee the 419 retry happens at most once. */
type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

function normaliseError(error: AxiosError<ApiEnvelope<unknown>>): ApiError {
  if (!error.response) {
    return { message: 'Unable to reach the server.', status: 0 }
  }

  const { status, data } = error.response
  const payload = data as ApiEnvelope<unknown> | undefined
  const serverMessage =
    typeof payload?.message === 'string' && payload.message.trim().length > 0
      ? payload.message
      : undefined

  return {
    message: messageFromStatus(status, serverMessage),
    status,
    errors: payload?.errors,
  }
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<ApiEnvelope<unknown>>) => {
    const status = error.response?.status
    const config = error.config as RetriableConfig | undefined

    // 419 — CSRF token mismatch. Refresh the cookie and retry once.
    if (status === 419 && config && !config._csrfRetried) {
      config._csrfRetried = true
      resetCsrfToken()

      try {
        await ensureCsrfToken()
        return await apiClient.request(config)
      } catch {
        return Promise.reject(normaliseError(error))
      }
    }

    if (status === 401) {
      resetCsrfToken()
      onUnauthorized?.()

      const path = window.location.pathname
      const isOnAuthRoute = AUTH_ROUTES.some((route) => path.startsWith(route))

      if (!isOnAuthRoute) {
        window.location.assign('/login')
      }
    }

    return Promise.reject(normaliseError(error))
  },
)

/** Pull the payload out of the response envelope. */
export function unwrap<T>(response: AxiosResponse<ApiEnvelope<T>>): T {
  return response.data.data
}

/** Pull rows + paginator out of a list response. */
export function unwrapList<T>(response: AxiosResponse<ApiEnvelope<T[]>>): {
  data: T[]
  meta: {
    current_page: number
    per_page: number
    last_page: number
    total: number
    from: number | null
    to: number | null
  }
} {
  const rows = response.data.data ?? []

  return {
    data: rows,
    meta:
      response.data.meta ??
      {
        current_page: 1,
        per_page: rows.length,
        last_page: 1,
        total: rows.length,
        from: rows.length > 0 ? 1 : null,
        to: rows.length > 0 ? rows.length : null,
      },
  }
}

export default apiClient
