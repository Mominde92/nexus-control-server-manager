import { BellRing, Check } from 'lucide-react'
import { Link } from 'react-router-dom'

import { DataTable, type DataTableColumn } from '@/components/common/data-table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useAcknowledgeAlert, useResolveAlert } from '@/hooks/use-server-alerts'
import { EMPTY, formatNumber, formatRelative } from '@/lib/format'
import type { PaginationMeta, SortDirection } from '@/types/api'
import type { ServerAlert } from '@/types/models'

export interface AlertTableProps {
  rows: ServerAlert[] | undefined
  meta?: PaginationMeta
  isLoading?: boolean
  sort?: string
  direction?: SortDirection
  onSort?: (column: string) => void
  onPageChange?: (page: number) => void
  onPerPageChange?: (perPage: number) => void
  /** Hides the server column when the table already lives on a server page. */
  hideServer?: boolean
  emptyMessage?: string
}

/**
 * The shared alert table: severity, the triggering measurement and the
 * acknowledge/resolve actions. Everything rendered is API-provided.
 */
export function AlertTable({
  rows,
  meta,
  isLoading,
  sort,
  direction,
  onSort,
  onPageChange,
  onPerPageChange,
  hideServer = false,
  emptyMessage = 'No alerts match the current filters.',
}: AlertTableProps) {
  const acknowledge = useAcknowledgeAlert()
  const resolve = useResolveAlert()
  const busy = acknowledge.isPending || resolve.isPending

  const columns: DataTableColumn<ServerAlert>[] = [
    {
      key: 'severity',
      header: 'Severity',
      sortable: true,
      render: (row) =>
        row.severity ? (
          <Badge variant={row.severity.variant}>{row.severity.label}</Badge>
        ) : (
          EMPTY
        ),
    },
    {
      key: 'title',
      header: 'Alert',
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.title ?? row.type ?? EMPTY}</p>
          {row.message ? (
            <p className="truncate text-xs text-muted-foreground">{row.message}</p>
          ) : null}
        </div>
      ),
    },
    ...(hideServer
      ? []
      : [
          {
            key: 'server',
            header: 'Server',
            render: (row: ServerAlert) =>
              row.server ? (
                <Link
                  to={`/servers/${row.server.id}`}
                  className="font-medium text-indigo-600 hover:underline"
                >
                  {row.server.name}
                </Link>
              ) : (
                EMPTY
              ),
          },
        ]),
    {
      key: 'observed_value',
      header: 'Observed',
      headerClassName: 'text-right',
      className: 'text-right tabular-nums',
      render: (row) =>
        row.observed_value === null ? (
          EMPTY
        ) : (
          <span>
            {formatNumber(row.observed_value, { maximumFractionDigits: 2 })}
            {row.threshold_value !== null ? (
              <span className="text-muted-foreground">
                {' / '}
                {formatNumber(row.threshold_value, { maximumFractionDigits: 2 })}
              </span>
            ) : null}
          </span>
        ),
    },
    {
      key: 'started_at',
      header: 'Started',
      sortable: true,
      render: (row) => (
        <div>
          <p>{formatRelative(row.started_at)}</p>
          {row.occurrences && row.occurrences > 1 ? (
            <p className="text-xs text-muted-foreground">
              {formatNumber(row.occurrences)} occurrences
            </p>
          ) : null}
        </div>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (row) =>
        row.resolved_at ? (
          <Badge variant="success">Resolved</Badge>
        ) : row.acknowledged_at ? (
          <Badge variant="secondary">
            Acknowledged{row.acknowledger ? ` · ${row.acknowledger.name}` : ''}
          </Badge>
        ) : (
          <Badge variant="warning">Open</Badge>
        ),
    },
  ]

  return (
    <DataTable
      columns={columns}
      rows={rows}
      meta={meta}
      isLoading={isLoading}
      sort={sort}
      direction={direction}
      onSort={onSort}
      onPageChange={onPageChange}
      onPerPageChange={onPerPageChange}
      emptyMessage={emptyMessage}
      rowActions={(row) => (
        <div className="flex justify-end gap-1">
          {!row.acknowledged_at && !row.resolved_at ? (
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={busy}
              onClick={() => acknowledge.mutate(row.id)}
            >
              <BellRing className="size-3.5" aria-hidden />
              Ack
            </Button>
          ) : null}
          {!row.resolved_at ? (
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={busy}
              onClick={() => resolve.mutate(row.id)}
            >
              <Check className="size-3.5" aria-hidden />
              Resolve
            </Button>
          ) : null}
        </div>
      )}
    />
  )
}

export default AlertTable
