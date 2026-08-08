import { useQuery } from '@tanstack/react-query'

import { queryKeys } from '@/lib/query-keys'
import { metaService } from '@/services/meta.service'
import type { ApiError, EnumOption } from '@/types/api'
import type { MetaOptions } from '@/types/models'

/**
 * All select options for the app. Effectively immutable for a session, so it is
 * fetched once and never refetched.
 */
export function useMetaOptions() {
  return useQuery<MetaOptions, ApiError>({
    queryKey: queryKeys.meta.options(),
    queryFn: () => metaService.options(),
    staleTime: Infinity,
    gcTime: Infinity,
  })
}

/** Convenience accessor for one option list; returns `[]` until loaded. */
export function useMetaOptionList(key: keyof MetaOptions): EnumOption[] {
  const { data } = useMetaOptions()

  return data?.[key] ?? []
}
