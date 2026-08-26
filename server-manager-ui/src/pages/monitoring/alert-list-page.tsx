import { BellRing } from 'lucide-react'

import { AlertTable } from '@/components/monitoring/alert-table'
import { Button } from '@/components/ui/button'
import { useListParams } from '@/hooks/use-list-params'
import { useMetaOptionList } from '@/hooks/use-meta'
import { useServerAlerts } from '@/hooks/use-server-alerts'
import { formatNumber } from '@/lib/format'

/** Alert lifecycle states the API filters on. A const tuple, never a TS enum. */
const STATUS_FILTERS = [
  { value: 'open', label: 'Open' },
  { value: 'acknowledged', label: 'Acknowledged' },
  { value: 'resolved', label: 'Resolved' },
] as const

/**
 * Every alert in the system, paginated server-side.
 *
 * Severity options come from `meta/options`; the status values are the API's own
 * documented filter vocabulary.
 */
export function AlertListPage() {
  const list = useListParams({ defaultSort: 'started_at' })
  const query = useServerAlerts(list.params)
  const severities = useMetaOptionList('alert_severities')

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-indigo-600 uppercase">
            <BellRing className="size-3.5" />
            Monitoring
          </p>
          <h1 className="mt-1 text-2xl font-bold">Server alerts</h1>
          <p className="text-sm text-muted-foreground">
            {query.data
              ? `${formatNumber(query.data.meta.total)} alerts match the current filters.`
              : 'Loading alerts…'}
          </p>
        </div>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
          Severity
          <select
            value={list.filters.severity ?? ''}
            onChange={(event) =>
              list.setFilter('severity', event.target.value || null)
            }
            className="h-9 min-w-36 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
          >
            <option value="">All severities</option>
            {severities.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
          Status
          <select
            value={list.filters.status ?? ''}
            onChange={(event) => list.setFilter('status', event.target.value || null)}
            className="h-9 min-w-36 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
          >
            <option value="">All statuses</option>
            {STATUS_FILTERS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <Button variant="ghost" onClick={list.resetFilters}>
          Reset
        </Button>
      </div>

      {query.error ? (
        <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-destructive">
          {query.error.message}
        </div>
      ) : null}

      <AlertTable
        rows={query.data?.data}
        meta={query.data?.meta}
        isLoading={query.isLoading}
        sort={list.sort}
        direction={list.direction}
        onSort={list.setSort}
        onPageChange={list.setPage}
        onPerPageChange={list.setPerPage}
      />
    </div>
  )
}

export default AlertListPage
