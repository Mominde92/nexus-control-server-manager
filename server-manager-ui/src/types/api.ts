/**
 * Transport-level types mirroring the Laravel API's response envelope.
 * Every endpoint returns the same shape, so these are shared by all services.
 */

/** Badge-able enum value emitted by the backend for status/type columns. */
export interface EnumOption {
  value: string
  label: string
  variant: 'default' | 'secondary' | 'destructive' | 'success' | 'warning' | 'info'
}

/** Laravel paginator metadata (present only on list endpoints). */
export interface PaginationMeta {
  current_page: number
  per_page: number
  last_page: number
  total: number
  from: number | null
  to: number | null
}

/** The universal response envelope. */
export interface ApiEnvelope<T> {
  success: boolean
  message: string
  data: T
  meta?: PaginationMeta
  errors?: Record<string, string[]>
}

/** A list response: `data` is an array and `meta` is guaranteed. */
export interface PaginatedEnvelope<T> extends ApiEnvelope<T[]> {
  meta: PaginationMeta
}

/**
 * A list result already unwrapped by the service layer — the rows plus the
 * paginator, because the UI needs both.
 */
export interface PaginatedResult<T> {
  data: T[]
  meta: PaginationMeta
}

/** Normalised error object produced by the axios response interceptor. */
export interface ApiError {
  message: string
  status: number
  errors?: Record<string, string[]>
}

export type SortDirection = 'asc' | 'desc'

/** Query parameters accepted by every list endpoint (plus module filters). */
export interface ListParams {
  page?: number
  per_page?: number
  search?: string
  sort?: string
  direction?: SortDirection
  with_trashed?: boolean
  [key: string]: unknown
}
