import { format, formatDistanceToNow, isValid, parseISO } from 'date-fns'

/** Rendered in place of any empty value so tables stay visually aligned. */
export const EMPTY = '—'

type DateInput = string | number | Date | null | undefined

function toDate(value: DateInput): Date | null {
  if (value === null || value === undefined || value === '') {
    return null
  }

  const date =
    value instanceof Date
      ? value
      : typeof value === 'number'
        ? new Date(value)
        : parseISO(value)

  return isValid(date) ? date : null
}

function safeFormat(value: DateInput, pattern: string): string {
  const date = toDate(value)

  return date ? format(date, pattern) : EMPTY
}

/** `05-08-2026` */
export function formatDate(value: DateInput): string {
  return safeFormat(value, 'dd-MM-yyyy')
}

/** `05-08-2026 04:00 PM` */
export function formatDateTime(value: DateInput): string {
  return safeFormat(value, 'dd-MM-yyyy hh:mm a')
}

/** `04:00 PM` */
export function formatTime(value: DateInput): string {
  return safeFormat(value, 'hh:mm a')
}

/** `3 days ago` */
export function formatRelative(value: DateInput): string {
  const date = toDate(value)

  return date ? formatDistanceToNow(date, { addSuffix: true }) : EMPTY
}

/** Locale-grouped integer/decimal, e.g. `1,204`. */
export function formatNumber(
  value: number | string | null | undefined,
  options?: Intl.NumberFormatOptions,
): string {
  if (value === null || value === undefined || value === '') {
    return EMPTY
  }

  const numeric = typeof value === 'number' ? value : Number(value)

  if (!Number.isFinite(numeric)) {
    return EMPTY
  }

  return new Intl.NumberFormat('en-US', options).format(numeric)
}

/**
 * Money with an ISO currency code. Unknown codes fall back to a plain amount
 * with the code appended rather than throwing.
 */
export function formatCurrency(
  amount: number | string | null | undefined,
  currency?: string | null,
): string {
  if (amount === null || amount === undefined || amount === '') {
    return EMPTY
  }

  const numeric = typeof amount === 'number' ? amount : Number(amount)

  if (!Number.isFinite(numeric)) {
    return EMPTY
  }

  const code = (currency ?? 'USD').toUpperCase()

  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: code,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(numeric)
  } catch {
    return `${formatNumber(numeric, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} ${code}`
  }
}

/** First-letter initials for avatar fallbacks (max two). */
export function initials(name: string | null | undefined): string {
  if (!name) {
    return '?'
  }

  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]!.toUpperCase())
    .join('')
}
