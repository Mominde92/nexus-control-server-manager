import * as React from 'react'
import { Activity, BarChart3, Gauge, TrendingDown, TrendingUp } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useMetaOptionList } from '@/hooks/use-meta'
import { useServerMetrics } from '@/hooks/use-servers'
import { formatDateTime, formatNumber } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { MetricRange } from '@/types/models'

export const METRIC_RANGES = ['1h', '6h', '24h', '7d', '30d', '90d'] as const

export interface MetricChartProps {
  serverId: number | string | undefined
  thresholds?: (type: string) => { warning?: number | null; critical?: number | null }
  className?: string
  height?: number
}

export function MetricChart({ serverId, thresholds, className, height = 320 }: MetricChartProps) {
  const metricTypes = useMetaOptionList('metric_types')
  const [type, setType] = React.useState<string>()
  const [range, setRange] = React.useState<MetricRange>('24h')
  const [hover, setHover] = React.useState<number | null>(null)
  const activeType = type ?? metricTypes[0]?.value
  const query = useServerMetrics(serverId, activeType, range)
  const points = query.data?.points ?? []
  const values = points.flatMap((point) => typeof point.avg === 'number' ? [point.avg] : [])
  const current = values.at(-1) ?? null
  const average = values.length ? values.reduce((sum, value) => sum + value, 0) / values.length : null
  const peak = values.length ? Math.max(...values) : null
  const low = values.length ? Math.min(...values) : null
  const unit = query.data?.unit ?? ''
  const isPercent = query.data?.is_percentage ?? activeType?.includes('percent') ?? false
  const ceiling = isPercent ? 100 : Math.max(peak ?? 1, 1) * 1.08
  const guides = query.data?.thresholds.warning != null || query.data?.thresholds.critical != null
    ? query.data.thresholds
    : (thresholds?.(activeType ?? '') ?? {})
  const hovered = hover == null ? undefined : points[hover]

  const stats = [
    { label: 'Current', value: current, icon: Activity, tone: 'text-indigo-600 bg-indigo-500/10' },
    { label: 'Average', value: average, icon: Gauge, tone: 'text-cyan-600 bg-cyan-500/10' },
    { label: 'Peak', value: peak, icon: TrendingUp, tone: 'text-rose-600 bg-rose-500/10' },
    { label: 'Low', value: low, icon: TrendingDown, tone: 'text-emerald-600 bg-emerald-500/10' },
  ]

  return (
    <div className={cn('space-y-6', className)}>
      <div className="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex items-center gap-3">
          <div className="grid size-11 place-items-center rounded-xl bg-indigo-500/10 text-indigo-600"><BarChart3 className="size-5" /></div>
          <div><h2 className="font-semibold">Performance analytics</h2><p className="text-xs text-muted-foreground">Explore resource usage over time</p></div>
        </div>
        <div className="flex flex-wrap gap-1 rounded-xl border bg-muted/30 p-1">
          {METRIC_RANGES.map((option) => <Button key={option} type="button" size="sm" variant="ghost" className={cn('h-8 px-3 text-xs', range === option && 'bg-background shadow-sm')} onClick={() => { setRange(option); setHover(null) }}>{option}</Button>)}
        </div>
      </div>

      <div className="flex gap-2 overflow-x-auto pb-1">
        {metricTypes.map((option) => <button key={option.value} type="button" onClick={() => { setType(option.value); setHover(null) }} className={cn('shrink-0 rounded-xl border px-4 py-2.5 text-sm font-medium transition', activeType === option.value ? 'border-indigo-500 bg-indigo-500 text-white shadow-sm shadow-indigo-500/20' : 'bg-background text-muted-foreground hover:border-indigo-300 hover:text-foreground')}>{option.label}</button>)}
      </div>

      {query.isLoading ? <Skeleton className="w-full rounded-2xl" style={{ height }} /> : query.error ? <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-5 text-sm text-destructive">{query.error.message}</div> : !points.length ? <div className="grid place-items-center rounded-2xl border border-dashed text-sm text-muted-foreground" style={{ height }}>No samples recorded for this range yet.</div> : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {stats.map((stat) => { const Icon = stat.icon; return <div key={stat.label} className="rounded-xl border bg-muted/10 p-4"><div className="flex items-center gap-2"><span className={cn('grid size-8 place-items-center rounded-lg', stat.tone)}><Icon className="size-4" /></span><span className="text-xs text-muted-foreground">{stat.label}</span></div><p className="mt-3 text-2xl font-bold tabular-nums">{formatNumber(stat.value, { maximumFractionDigits: 1 })}<span className="ml-1 text-xs font-medium text-muted-foreground">{unit}</span></p></div> })}
          </div>

          <div className="relative overflow-hidden rounded-2xl border bg-muted/10 p-5">
            <div className="mb-5 flex items-center justify-between"><div><p className="text-sm font-semibold">{query.data?.label ?? activeType}</p><p className="text-xs text-muted-foreground">{range} activity · {points.length} samples</p></div><div className="flex gap-3 text-[11px]"><span className="flex items-center gap-1.5 text-amber-600"><span className="h-px w-4 border-t border-dashed border-amber-500" />Warning</span><span className="flex items-center gap-1.5 text-rose-600"><span className="h-px w-4 border-t border-dashed border-rose-500" />Critical</span></div></div>

            <div className="relative pl-9" style={{ height }}>
              {[0, 25, 50, 75, 100].map((tick) => <div key={tick} className="absolute right-0 left-9 border-t border-dashed border-border/60" style={{ bottom: `${tick}%` }}><span className="absolute -left-9 -top-2 text-[9px] text-muted-foreground">{formatNumber(ceiling * tick / 100, { maximumFractionDigits: 0 })}</span></div>)}
              {guides.warning != null ? <div className="absolute right-0 left-9 z-10 border-t border-dashed border-amber-500/80" style={{ bottom: `${Math.min(100, guides.warning / ceiling * 100)}%` }} /> : null}
              {guides.critical != null ? <div className="absolute right-0 left-9 z-10 border-t border-dashed border-rose-500/80" style={{ bottom: `${Math.min(100, guides.critical / ceiling * 100)}%` }} /> : null}
              <div className="absolute inset-y-0 right-0 left-9 flex items-end gap-[2px]">
                {points.map((point, index) => { const value = point.avg ?? 0; const active = hover === index; return <button key={`${point.t}-${index}`} type="button" aria-label={`${formatDateTime(point.t)}: ${value}${unit}`} onMouseEnter={() => setHover(index)} onMouseLeave={() => setHover(null)} className="group relative flex h-full min-w-0 flex-1 items-end focus:outline-none"><span className={cn('w-full rounded-t-sm bg-gradient-to-t from-indigo-600 to-cyan-400 transition-all duration-200', active ? 'opacity-100 brightness-110' : 'opacity-70 group-hover:opacity-100')} style={{ height: `${Math.max(2, Math.min(100, value / ceiling * 100))}%` }} /></button> })}
              </div>
            </div>
            <div className="mt-3 flex justify-between pl-9 text-[10px] text-muted-foreground"><span>{formatDateTime(points[0]?.t)}</span><span>{formatDateTime(points.at(-1)?.t)}</span></div>

            {hovered ? <div className="pointer-events-none absolute top-20 right-7 z-20 min-w-44 rounded-xl border bg-popover p-3 text-xs shadow-xl"><p className="font-semibold">{formatDateTime(hovered.t)}</p><div className="mt-2 grid grid-cols-3 gap-3 text-muted-foreground"><span>Avg<br /><b className="text-foreground">{formatNumber(hovered.avg, { maximumFractionDigits: 1 })}</b></span><span>Min<br /><b className="text-foreground">{formatNumber(hovered.min, { maximumFractionDigits: 1 })}</b></span><span>Max<br /><b className="text-foreground">{formatNumber(hovered.max, { maximumFractionDigits: 1 })}</b></span></div></div> : null}
          </div>
        </>
      )}
    </div>
  )
}

export default MetricChart
