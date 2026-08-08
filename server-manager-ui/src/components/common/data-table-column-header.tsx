import type { ReactNode } from 'react'
import { ChevronDown, ChevronsUpDown, ChevronUp } from 'lucide-react'

import { cn } from '@/lib/utils'
import type { SortDirection } from '@/types/api'

export interface DataTableColumnHeaderProps {
  title: ReactNode
  column: string
  sort?: string
  direction?: SortDirection
  onSort?: (column: string) => void
  className?: string
}

/**
 * Standalone sortable header button — useful when a page renders a bespoke
 * table but still wants the same sorting affordance as `DataTable`.
 */
export function DataTableColumnHeader({
  title,
  column,
  sort,
  direction = 'asc',
  onSort,
  className,
}: DataTableColumnHeaderProps) {
  const isActive = sort === column

  if (!onSort) {
    return <span className={className}>{title}</span>
  }

  return (
    <button
      type="button"
      onClick={() => onSort(column)}
      className={cn(
        '-mx-2 inline-flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
        className,
      )}
    >
      {title}
      {isActive ? (
        direction === 'asc' ? (
          <ChevronUp className="size-3.5" aria-hidden />
        ) : (
          <ChevronDown className="size-3.5" aria-hidden />
        )
      ) : (
        <ChevronsUpDown className="size-3.5 opacity-40" aria-hidden />
      )}
    </button>
  )
}

export default DataTableColumnHeader
