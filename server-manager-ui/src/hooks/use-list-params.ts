import * as React from 'react'
import { useSearchParams } from 'react-router-dom'

import { useDebounce } from '@/hooks/use-debounce'
import type { ListParams, SortDirection } from '@/types/api'

/** Keys the list machinery owns; anything else in the URL is a module filter. */
const RESERVED_KEYS = [
  'page',
  'per_page',
  'search',
  'sort',
  'direction',
  'with_trashed',
] as const

export interface UseListParamsOptions {
  defaultPerPage?: number
  defaultSort?: string
  defaultDirection?: SortDirection
}

export interface UseListParamsResult {
  /** Ready to hand straight to a `useList(params)` hook. */
  params: ListParams
  /** Uncontrolled-friendly mirror of the search box value (not debounced). */
  searchInput: string
  filters: Record<string, string>
  setPage: (page: number) => void
  setPerPage: (perPage: number) => void
  setSearch: (value: string) => void
  /** Toggles asc/desc when the same column is clicked again. */
  setSort: (column: string) => void
  setFilter: (key: string, value: string | null | undefined) => void
  setWithTrashed: (value: boolean) => void
  resetFilters: () => void
  sort: string | undefined
  direction: SortDirection
}

/**
 * Keep list state (page, page size, search, sorting, filters) in the URL query
 * string so any list view is shareable, bookmarkable and survives a refresh or
 * a back-navigation from a detail page.
 *
 * The search box is debounced by 300 ms before it reaches the URL, so typing
 * does not spam history entries or requests.
 */
export function useListParams(
  options: UseListParamsOptions = {},
): UseListParamsResult {
  const {
    defaultPerPage = 15,
    defaultSort,
    defaultDirection = 'desc',
  } = options

  const [searchParams, setSearchParams] = useSearchParams()

  const urlSearch = searchParams.get('search') ?? ''
  const [searchInput, setSearchInput] = React.useState(urlSearch)
  const debouncedSearch = useDebounce(searchInput, 300)

  /** Merge a patch into the query string, dropping empty values. */
  const patchParams = React.useCallback(
    (patch: Record<string, string | null | undefined>, resetPage = true) => {
      setSearchParams(
        (current) => {
          const next = new URLSearchParams(current)

          for (const [key, value] of Object.entries(patch)) {
            if (value === null || value === undefined || value === '') {
              next.delete(key)
            } else {
              next.set(key, value)
            }
          }

          if (resetPage && !('page' in patch)) {
            next.delete('page')
          }

          return next
        },
        { replace: true },
      )
    },
    [setSearchParams],
  )

  // Push the debounced search term into the URL once typing settles.
  React.useEffect(() => {
    if (debouncedSearch === urlSearch) {
      return
    }

    patchParams({ search: debouncedSearch })
  }, [debouncedSearch, urlSearch, patchParams])

  // Adopt external URL changes (back button, a link with a preset filter).
  React.useEffect(() => {
    setSearchInput((current) => (current === urlSearch ? current : urlSearch))
  }, [urlSearch])

  const filters = React.useMemo(() => {
    const collected: Record<string, string> = {}

    for (const [key, value] of searchParams.entries()) {
      if (!(RESERVED_KEYS as readonly string[]).includes(key)) {
        collected[key] = value
      }
    }

    return collected
  }, [searchParams])

  const sort = searchParams.get('sort') ?? defaultSort
  const direction = (searchParams.get('direction') as SortDirection | null) ??
    defaultDirection

  const params = React.useMemo<ListParams>(() => {
    const built: ListParams = {
      page: Number(searchParams.get('page') ?? 1),
      per_page: Number(searchParams.get('per_page') ?? defaultPerPage),
      ...filters,
    }

    if (urlSearch) {
      built.search = urlSearch
    }

    if (sort) {
      built.sort = sort
      built.direction = direction
    }

    if (searchParams.get('with_trashed') === '1') {
      built.with_trashed = true
    }

    return built
  }, [searchParams, filters, urlSearch, sort, direction, defaultPerPage])

  const setPage = React.useCallback(
    (page: number) => {
      patchParams({ page: page > 1 ? String(page) : null }, false)
    },
    [patchParams],
  )

  const setPerPage = React.useCallback(
    (perPage: number) => {
      patchParams({ per_page: String(perPage) })
    },
    [patchParams],
  )

  const setSearch = React.useCallback((value: string) => {
    setSearchInput(value)
  }, [])

  const setSort = React.useCallback(
    (column: string) => {
      const nextDirection: SortDirection =
        sort === column && direction === 'asc' ? 'desc' : 'asc'

      patchParams({ sort: column, direction: nextDirection })
    },
    [sort, direction, patchParams],
  )

  const setFilter = React.useCallback(
    (key: string, value: string | null | undefined) => {
      patchParams({ [key]: value })
    },
    [patchParams],
  )

  const setWithTrashed = React.useCallback(
    (value: boolean) => {
      patchParams({ with_trashed: value ? '1' : null })
    },
    [patchParams],
  )

  const resetFilters = React.useCallback(() => {
    setSearchInput('')
    setSearchParams(new URLSearchParams(), { replace: true })
  }, [setSearchParams])

  return {
    params,
    searchInput,
    filters,
    setPage,
    setPerPage,
    setSearch,
    setSort,
    setFilter,
    setWithTrashed,
    resetFilters,
    sort,
    direction,
  }
}

export default useListParams
