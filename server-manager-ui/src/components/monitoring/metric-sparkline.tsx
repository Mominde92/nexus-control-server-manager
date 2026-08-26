import { cn } from '@/lib/utils'
import type { MetricPoint } from '@/types/models'

export interface MetricSparklineProps {
  points: MetricPoint[] | null | undefined
  unit?: string | null
  height?: number
  width?: number
  stroke?: string
  /** Optional horizontal guide lines, drawn only when inside the value range. */
  warningThreshold?: number | null
  criticalThreshold?: number | null
  className?: string
  label?: string
}

/** Numeric samples only — nulls are gaps and must not become zeros. */
function usableValues(points: MetricPoint[] | null | undefined): number[] {
  if (!points) {
    return []
  }

  return points
    .map((point) => point.avg)
    .filter((value): value is number => typeof value === 'number' && Number.isFinite(value))
}

/**
 * Inline-SVG sparkline. No chart library, no per-row fetching: it draws whatever
 * points it is handed and renders a dash when there are none.
 *
 * Guards every divisor — a single point, or a perfectly flat series, both draw a
 * centred horizontal line instead of dividing by zero.
 */
export function MetricSparkline({
  points,
  unit,
  height = 28,
  width = 96,
  stroke = 'currentColor',
  warningThreshold,
  criticalThreshold,
  className,
  label,
}: MetricSparklineProps) {
  const values = usableValues(points)

  if (values.length === 0) {
    return <span className="text-muted-foreground">—</span>
  }

  const padding = 2
  const innerHeight = Math.max(height - padding * 2, 1)
  const innerWidth = Math.max(width - padding * 2, 1)

  const rawMin = Math.min(...values)
  const rawMax = Math.max(...values)
  const guides = [warningThreshold, criticalThreshold].filter(
    (value): value is number => typeof value === 'number' && Number.isFinite(value),
  )

  // Include guide lines in the domain so a threshold marker is never clipped.
  const min = guides.length > 0 ? Math.min(rawMin, ...guides) : rawMin
  const max = guides.length > 0 ? Math.max(rawMax, ...guides) : rawMax
  const span = max - min

  const toY = (value: number): number =>
    span === 0
      ? padding + innerHeight / 2
      : padding + innerHeight - ((value - min) / span) * innerHeight

  const stepX = values.length > 1 ? innerWidth / (values.length - 1) : 0
  const coordinates = values.map((value, index) => ({
    x: values.length > 1 ? padding + index * stepX : padding + innerWidth / 2,
    y: toY(value),
  }))

  const line = coordinates
    .map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
    .join(' ')

  const last = values[values.length - 1]!
  const lastPoint = coordinates[coordinates.length - 1]!
  const title =
    label ?? `Latest ${last}${unit ? ` ${unit}` : ''} over ${values.length} samples`

  return (
    <svg
      viewBox={`0 0 ${width} ${height}`}
      width={width}
      height={height}
      role="img"
      aria-label={title}
      className={cn('overflow-visible', className)}
      preserveAspectRatio="none"
    >
      <title>{title}</title>

      {guides.map((value, index) => (
        <line
          key={`guide-${index}`}
          x1={padding}
          x2={padding + innerWidth}
          y1={toY(value)}
          y2={toY(value)}
          stroke={index === 0 ? 'var(--color-amber-500, #f59e0b)' : 'var(--color-red-500, #ef4444)'}
          strokeWidth={0.75}
          strokeDasharray="3 2"
          opacity={0.7}
        />
      ))}

      {values.length === 1 ? (
        <line
          x1={padding}
          x2={padding + innerWidth}
          y1={lastPoint.y}
          y2={lastPoint.y}
          stroke={stroke}
          strokeWidth={1.5}
          strokeLinecap="round"
        />
      ) : (
        <path
          d={line}
          fill="none"
          stroke={stroke}
          strokeWidth={1.5}
          strokeLinejoin="round"
          strokeLinecap="round"
        />
      )}

      <circle cx={lastPoint.x} cy={lastPoint.y} r={1.75} fill={stroke} />
    </svg>
  )
}

export default MetricSparkline
