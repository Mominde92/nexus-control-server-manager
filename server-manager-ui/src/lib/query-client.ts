import { QueryClient } from '@tanstack/react-query'

import type { ApiError } from '@/types/api'

/** 4xx responses are deterministic — retrying them just wastes a round trip. */
function shouldRetry(failureCount: number, error: unknown): boolean {
  const status = (error as ApiError | undefined)?.status

  if (typeof status === 'number' && status >= 400 && status < 500) {
    return false
  }

  return failureCount < 1
}

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: shouldRetry,
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: false,
    },
  },
})

export default queryClient
