import type { ReactNode } from 'react'
import { Eye, MoreHorizontal, Pencil, RotateCcw, Trash2 } from 'lucide-react'

import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

export interface RowActionsProps {
  onView?: () => void
  onEdit?: () => void
  onDelete?: () => void
  onRestore?: () => void
  canEdit?: boolean
  canDelete?: boolean
  canRestore?: boolean
  /** Extra menu items appended before the destructive section. */
  children?: ReactNode
  label?: string
}

/** The standard View / Edit / Delete overflow menu used in every list table. */
export function RowActions({
  onView,
  onEdit,
  onDelete,
  onRestore,
  canEdit = true,
  canDelete = true,
  canRestore = false,
  children,
  label = 'Open row actions',
}: RowActionsProps) {
  const showEdit = Boolean(onEdit) && canEdit
  const showDelete = Boolean(onDelete) && canDelete
  const showRestore = Boolean(onRestore) && canRestore

  if (!onView && !showEdit && !showDelete && !showRestore && !children) {
    return null
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="size-8"
          onClick={(event) => {
            event.stopPropagation()
          }}
        >
          <MoreHorizontal className="size-4" aria-hidden />
          <span className="sr-only">{label}</span>
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-44">
        {onView ? (
          <DropdownMenuItem onSelect={() => onView()}>
            <Eye className="size-4" aria-hidden />
            View
          </DropdownMenuItem>
        ) : null}

        {showEdit ? (
          <DropdownMenuItem onSelect={() => onEdit?.()}>
            <Pencil className="size-4" aria-hidden />
            Edit
          </DropdownMenuItem>
        ) : null}

        {showRestore ? (
          <DropdownMenuItem onSelect={() => onRestore?.()}>
            <RotateCcw className="size-4" aria-hidden />
            Restore
          </DropdownMenuItem>
        ) : null}

        {children}

        {showDelete ? (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              variant="destructive"
              onSelect={() => onDelete?.()}
            >
              <Trash2 className="size-4" aria-hidden />
              Delete
            </DropdownMenuItem>
          </>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

export default RowActions
