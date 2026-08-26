import { Badge } from '@/components/ui/badge'
import { EMPTY } from '@/lib/format'
import type { EnumOption } from '@/types/api'

export interface HealthBadgeProps {
  health: EnumOption | null | undefined
  className?: string
  /** Rendered instead of a badge when the API sent no health value. */
  fallback?: string
}

/**
 * Render a server's health.
 *
 * Both the label and the badge colour come from the API's `{value,label,variant}`
 * payload, so adding a new health state server-side needs no change here.
 */
export function HealthBadge({
  health,
  className,
  fallback = EMPTY,
}: HealthBadgeProps) {
  if (!health) {
    return <span className="text-muted-foreground">{fallback}</span>
  }

  return (
    <Badge variant={health.variant} className={className}>
      {health.label}
    </Badge>
  )
}

export default HealthBadge
