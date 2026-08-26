import { Download, PlugZap, Plus, PowerOff, Search } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'

import { DataTable, type DataTableColumn } from '@/components/common/data-table'
import { RowActions } from '@/components/common/row-actions'
import { HealthBadge } from '@/components/monitoring/health-badge'
import { MetricSparkline } from '@/components/monitoring/metric-sparkline'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DropdownMenuItem } from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { useListParams } from '@/hooks/use-list-params'
import { useMetaOptionList } from '@/hooks/use-meta'
import {
  exportServers,
  useDisableMonitoring,
  useEnableMonitoring,
  useServers,
} from '@/hooks/use-servers'
import { EMPTY, formatNumber, formatRelative } from '@/lib/format'
import type { EnumOption } from '@/types/api'
import type { MetricPoint, Server } from '@/types/models'

/** The metric a row sparkline prefers, when the payload happens to carry it. */
const ROW_METRIC = 'cpu_percent'

function FilterSelect({
  label,
  value,
  options,
  onChange,
}: {
  label: string
  value: string
  options: EnumOption[]
  onChange: (next: string) => void
}) {
  return (
    <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
      {label}
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="h-9 min-w-36 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
      >
        <option value="">All</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  )
}

/** Recent points the list payload already carries, if any. Never fetched here. */
function rowPoints(server: Server): MetricPoint[] {
  const series = server.recent_metrics?.[ROW_METRIC]

  return Array.isArray(series) ? series : []
}

function rowLatest(server: Server): string {
  const latest = server.latest_metrics?.find(
    (metric) => metric.type.value === ROW_METRIC,
  )

  if (!latest || latest.value === null) {
    return EMPTY
  }

  return `${formatNumber(latest.value, { maximumFractionDigits: 1 })}${
    latest.unit ? ` ${latest.unit}` : ''
  }`
}

/**
 * The server fleet.
 *
 * Rows, page size, total count and every filter option come from the API — the
 * page has no knowledge of how many servers exist or what they are called. No
 * metric series is fetched per row: the sparkline draws only points the list
 * payload already embedded, otherwise it falls back to the latest value.
 */
export function ServerListPage() {
  const list = useListParams({ defaultSort: 'name', defaultDirection: 'asc' })
  const navigate = useNavigate()
  const query = useServers(list.params)
  const osOptions = useMetaOptionList('server_os')
  const healthOptions = useMetaOptionList('server_health')
  const enable = useEnableMonitoring()
  const disable = useDisableMonitoring()

  const columns: DataTableColumn<Server>[] = [
    {
      key: 'name',
      header: 'Server',
      sortable: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.name}</p>
          <p className="truncate text-xs text-muted-foreground">
            {row.hostname ?? row.ip_address ?? EMPTY}
          </p>
        </div>
      ),
    },
    {
      key: 'os',
      header: 'OS',
      render: (row) =>
        row.os ? (
          <div>
            <Badge variant={row.os.variant}>{row.os.label}</Badge>
            {row.os_version ? (
              <p className="mt-1 text-xs text-muted-foreground">{row.os_version}</p>
            ) : null}
          </div>
        ) : (
          EMPTY
        ),
    },
    {
      key: 'health',
      header: 'Health',
      sortable: true,
      render: (row) => (
        <div className="flex items-center gap-2">
          <HealthBadge health={row.health} />
          {row.monitoring_enabled ? null : (
            <span className="text-xs text-muted-foreground">unmonitored</span>
          )}
        </div>
      ),
    },
    {
      key: 'last_heartbeat_at',
      header: 'Last heartbeat',
      sortable: true,
      render: (row) => (
        <div>
          <p className={row.is_stale ? 'text-destructive' : undefined}>
            {formatRelative(row.last_heartbeat_at)}
          </p>
          {row.agent_version ? (
            <p className="text-xs text-muted-foreground">agent {row.agent_version}</p>
          ) : null}
        </div>
      ),
    },
    {
      key: 'open_alert_count',
      header: 'Alerts',
      sortable: true,
      headerClassName: 'text-right',
      className: 'text-right',
      render: (row) =>
        row.open_alert_count > 0 ? (
          <Badge variant="destructive">{formatNumber(row.open_alert_count)}</Badge>
        ) : (
          <span className="text-muted-foreground tabular-nums">0</span>
        ),
    },
    {
      key: 'cpu',
      header: 'CPU',
      render: (row) => {
        const points = rowPoints(row)

        return points.length > 0 ? (
          <MetricSparkline
            points={points}
            unit="%"
            warningThreshold={row.cpu_warning_threshold}
            criticalThreshold={row.cpu_critical_threshold}
            className="text-indigo-500"
          />
        ) : (
          <span className="tabular-nums">{rowLatest(row)}</span>
        )
      },
    },
  ]

  async function exportCsv() {
    try {
      await exportServers(list.params)
    } catch {
      toast.error('Could not export the server list.')
    }
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-semibold tracking-wider text-indigo-600 uppercase">
            Monitoring
          </p>
          <h1 className="mt-1 text-2xl font-bold">Servers</h1>
          <p className="text-sm text-muted-foreground">
            {query.data
              ? `${formatNumber(query.data.meta.total)} servers reported by the API.`
              : 'Loading the fleet…'}
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => void exportCsv()}>
            <Download />
            Export CSV
          </Button>
          <Button onClick={() => navigate('/servers/new')}>
            <Plus />
            Add server
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <div className="relative min-w-56 flex-1">
          <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
          <Input
            className="pl-9"
            placeholder="Search servers…"
            value={list.searchInput}
            onChange={(event) => list.setSearch(event.target.value)}
          />
        </div>

        <FilterSelect
          label="Operating system"
          value={list.filters.os ?? ''}
          options={osOptions}
          onChange={(next) => list.setFilter('os', next || null)}
        />
        <FilterSelect
          label="Health"
          value={list.filters.health ?? ''}
          options={healthOptions}
          onChange={(next) => list.setFilter('health', next || null)}
        />
        <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
          Monitoring
          <select
            value={list.filters.monitoring_enabled ?? ''}
            onChange={(event) =>
              list.setFilter('monitoring_enabled', event.target.value || null)
            }
            className="h-9 min-w-32 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
          >
            <option value="">All</option>
            <option value="1">Enabled</option>
            <option value="0">Disabled</option>
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

      <DataTable
        columns={columns}
        rows={query.data?.data}
        meta={query.data?.meta}
        isLoading={query.isLoading}
        sort={list.sort}
        direction={list.direction}
        onSort={list.setSort}
        onPageChange={list.setPage}
        onPerPageChange={list.setPerPage}
        emptyMessage="No servers match the current filters."
        onRowClick={(row) => navigate(`/servers/${row.id}`)}
        rowActions={(row) => (
          <RowActions
            onView={() => navigate(`/servers/${row.id}`)}
            onEdit={() => navigate(`/servers/${row.id}/edit`)}
            onDelete={() => navigate(`/servers/${row.id}?action=delete`)}
          >
            {row.monitoring_enabled ? (
              <DropdownMenuItem onSelect={() => disable.mutate(row.id)}>
                <PowerOff className="size-4" aria-hidden />
                Disable monitoring
              </DropdownMenuItem>
            ) : (
              <DropdownMenuItem onSelect={() => enable.mutate(row.id)}>
                <PlugZap className="size-4" aria-hidden />
                Enable monitoring
              </DropdownMenuItem>
            )}
          </RowActions>
        )}
      />
    </div>
  )
}

export default ServerListPage
