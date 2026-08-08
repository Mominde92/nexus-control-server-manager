import type { ReactNode } from 'react'
import { ChevronDown, ChevronsUpDown, ChevronUp } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { cn } from '@/lib/utils'
import type { PaginationMeta, SortDirection } from '@/types/api'

/** A single column definition. `key` doubles as the server-side sort field. */
export interface DataTableColumn<T> {
  key: string
  header: ReactNode
  sortable?: boolean
  className?: string
  /** Header cell class, for right-aligning numeric columns etc. */
  headerClassName?: string
  render?: (row: T) => ReactNode
}

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[]
  rows: T[] | undefined
  meta?: PaginationMeta
  isLoading?: boolean
  sort?: string
  direction?: SortDirection
  onSort?: (column: string) => void
  onPageChange?: (page: number) => void
  onPerPageChange?: (perPage: number) => void
  emptyMessage?: string
  rowActions?: (row: T) => ReactNode
  /** Pulls `row[getRowId]` for React keys; defaults to `id`. */
  getRowId?: (row: T) => string | number
  onRowClick?: (row: T) => void
  className?: string
}

const PER_PAGE_OPTIONS = [15, 25, 50, 100]

function SortIcon({
  active,
  direction,
}: {
  active: boolean
  direction: SortDirection
}) {
  if (!active) {
    return <ChevronsUpDown className="size-3.5 opacity-40" aria-hidden />
  }

  return direction === 'asc' ? (
    <ChevronUp className="size-3.5" aria-hidden />
  ) : (
    <ChevronDown className="size-3.5" aria-hidden />
  )
}

/**
 * Server-driven data table.
 *
 * Sorting, filtering and pagination all happen in the API, so this component is
 * deliberately dumb: it renders what it is given and reports intent upwards.
 */
export function DataTable<T>({
  columns,
  rows,
  meta,
  isLoading = false,
  sort,
  direction = 'asc',
  onSort,
  onPageChange,
  onPerPageChange,
  emptyMessage = 'No records found.',
  rowActions,
  getRowId,
  onRowClick,
  className,
}: DataTableProps<T>) {
  const columnCount = columns.length + (rowActions ? 1 : 0)
  const showEmpty = !isLoading && (rows?.length ?? 0) === 0
  const currentPage = meta?.current_page ?? 1
  const lastPage = meta?.last_page ?? 1

  const resolveId = (row: T, index: number): string | number => {
    if (getRowId) {
      return getRowId(row)
    }

    const candidate = (row as { id?: string | number }).id

    return candidate ?? index
  }

  return (
    <div
      className={cn(
        'overflow-hidden rounded-xl border border-border bg-card shadow-sm',
        className,
      )}
    >
      <div className="overflow-x-auto">
        <Table className="text-sm">
          <TableHeader>
            <TableRow className="bg-muted/40 hover:bg-muted/40">
              {columns.map((column) => {
                const isActive = sort === column.key

                return (
                  <TableHead
                    key={column.key}
                    aria-sort={
                      isActive
                        ? direction === 'asc'
                          ? 'ascending'
                          : 'descending'
                        : undefined
                    }
                    className={cn(
                      'h-11 text-xs font-semibold tracking-wide text-muted-foreground uppercase',
                      column.headerClassName,
                    )}
                  >
                    {column.sortable && onSort ? (
                      <button
                        type="button"
                        onClick={() => onSort(column.key)}
                        className="-mx-2 inline-flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                      >
                        {column.header}
                        <SortIcon active={isActive} direction={direction} />
                      </button>
                    ) : (
                      column.header
                    )}
                  </TableHead>
                )
              })}
              {rowActions ? (
                <TableHead className="h-11 w-12 text-right text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                  <span className="sr-only">Actions</span>
                </TableHead>
              ) : null}
            </TableRow>
          </TableHeader>

          <TableBody>
            {isLoading
              ? Array.from({ length: 6 }, (_, rowIndex) => (
                  <TableRow key={`skeleton-${rowIndex}`}>
                    {Array.from({ length: columnCount }, (__, cellIndex) => (
                      <TableCell key={`skeleton-cell-${cellIndex}`}>
                        <Skeleton className="h-4 w-full max-w-40" />
                      </TableCell>
                    ))}
                  </TableRow>
                ))
              : rows?.map((row, index) => (
                  <TableRow
                    key={resolveId(row, index)}
                    onClick={onRowClick ? () => onRowClick(row) : undefined}
                    className={cn(onRowClick && 'cursor-pointer')}
                  >
                    {columns.map((column) => (
                      <TableCell
                        key={column.key}
                        className={cn('py-3 align-middle', column.className)}
                      >
                        {column.render
                          ? column.render(row)
                          : String(
                              (row as Record<string, unknown>)[column.key] ?? '—',
                            )}
                      </TableCell>
                    ))}
                    {rowActions ? (
                      <TableCell className="py-3 text-right">
                        {rowActions(row)}
                      </TableCell>
                    ) : null}
                  </TableRow>
                ))}

            {showEmpty ? (
              <TableRow className="hover:bg-transparent">
                <TableCell
                  colSpan={columnCount}
                  className="h-32 text-center text-muted-foreground"
                >
                  {emptyMessage}
                </TableCell>
              </TableRow>
            ) : null}
          </TableBody>
        </Table>
      </div>

      {meta ? (
        <div className="flex flex-col gap-3 border-t border-border px-4 py-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-3">
            <span>
              {meta.total === 0
                ? 'No results'
                : `Showing ${meta.from ?? 0} to ${meta.to ?? 0} of ${meta.total}`}
            </span>

            {onPerPageChange ? (
              <label className="flex items-center gap-2">
                <span className="sr-only">Rows per page</span>
                <select
                  value={meta.per_page}
                  onChange={(event) => {
                    onPerPageChange(Number(event.target.value))
                  }}
                  className="h-8 rounded-md border border-input bg-background px-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                  {PER_PAGE_OPTIONS.map((option) => (
                    <option key={option} value={option}>
                      {option} / page
                    </option>
                  ))}
                </select>
              </label>
            ) : null}
          </div>

          {onPageChange ? (
            <div className="flex items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={currentPage <= 1}
                onClick={() => onPageChange(currentPage - 1)}
              >
                Previous
              </Button>
              <span className="px-1 tabular-nums">
                Page {currentPage} of {Math.max(lastPage, 1)}
              </span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={currentPage >= lastPage}
                onClick={() => onPageChange(currentPage + 1)}
              >
                Next
              </Button>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}

export default DataTable
