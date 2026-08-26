import { Link } from 'react-router-dom'

import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { formatNumber } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { MonitoringSummary } from '@/types/models'

export interface ServerHealthSummaryProps {
  summary: MonitoringSummary | undefined
  isLoading?: boolean
  className?: string
  /** Renders four health tiles only, for the compact dashboard row. */
  compact?: boolean
}

/**
 * Which counter maps to which server-list filter.
 *
 * The keys are the summary payload's own field names, so the tiles are a
 * projection of the API response — not a hand-maintained list of servers.
 */
const TILES = [
  { key: 'total', label: 'Servers', to: '/servers', tone: 'text-slate-600' },
  {
    key: 'healthy',
    label: 'Healthy',
    to: '/servers?health=healthy',
    tone: 'text-emerald-600',
  },
  {
    key: 'warning',
    label: 'Warning',
    to: '/servers?health=warning',
    tone: 'text-amber-600',
  },
  {
    key: 'critical',
    label: 'Critical',
    to: '/servers?health=critical',
    tone: 'text-red-600',
  },
  {
    key: 'offline',
    label: 'Offline',
    to: '/servers?health=offline',
    tone: 'text-slate-500',
  },
  {
    key: 'pending',
    label: 'Pending',
    to: '/servers?health=pending',
    tone: 'text-sky-600',
  },
] as const satisfies readonly {
  key: keyof MonitoringSummary
  label: string
  to: string
  tone: string
}[]

const COMPACT_KEYS: readonly (keyof MonitoringSummary)[] = [
  'healthy',
  'warning',
  'critical',
  'offline',
]

/**
 * Fleet health tiles.
 *
 * Every number comes from `GET /monitoring/summary`; each tile links into the
 * server list pre-filtered on that health value.
 */
export function ServerHealthSummary({
  summary,
  isLoading = false,
  className,
  compact = false,
}: ServerHealthSummaryProps) {
  const tiles = compact
    ? TILES.filter((tile) => COMPACT_KEYS.includes(tile.key))
    : TILES

  return (
    <div
      className={cn(
        'grid gap-3',
        compact ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3 xl:grid-cols-6',
        className,
      )}
    >
      {tiles.map((tile) => (
        <Link key={tile.key} to={tile.to} className="group">
          <Card className="h-full transition-shadow group-hover:shadow-md">
            <CardContent className="p-4">
              <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {tile.label}
              </p>
              {isLoading ? (
                <Skeleton className="mt-2 h-8 w-16" />
              ) : (
                <p
                  className={cn(
                    'mt-1.5 text-2xl font-bold tabular-nums',
                    tile.tone,
                  )}
                >
                  {formatNumber(summary?.[tile.key] ?? 0)}
                </p>
              )}
              {!compact && tile.key === 'total' ? (
                <p className="mt-1 text-[11px] text-muted-foreground">
                  {formatNumber(summary?.monitored ?? 0)} monitored
                </p>
              ) : null}
            </CardContent>
          </Card>
        </Link>
      ))}
    </div>
  )
}

export default ServerHealthSummary
