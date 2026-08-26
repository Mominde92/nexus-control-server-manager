import * as React from 'react'
import { Activity, Cpu, Database, MemoryStick, TrendingDown, TrendingUp } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useLatestMetrics, useServerMetrics } from '@/hooks/use-servers'
import { formatNumber, formatRelative } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { LatestMetric, MetricPoint, MetricRange } from '@/types/models'

const RANGES: MetricRange[] = ['1h', '6h', '24h', '7d']
const METRICS: Array<{ type: string; title: string; subtitle: string; icon: LucideIcon; color: string; tone: string }> = [
  { type: 'cpu_percent', title: 'CPU usage', subtitle: 'Processor load', icon: Cpu, color: '#6366f1', tone: 'bg-indigo-500/10 text-indigo-600' },
  { type: 'memory_percent', title: 'Memory usage', subtitle: 'Physical RAM', icon: MemoryStick, color: '#06b6d4', tone: 'bg-cyan-500/10 text-cyan-600' },
  { type: 'disk_percent', title: 'Disk usage', subtitle: 'Primary volume', icon: Database, color: '#8b5cf6', tone: 'bg-violet-500/10 text-violet-600' },
]

function metricValues(points: MetricPoint[]) {
  const values = points.flatMap((point) => typeof point.avg === 'number' ? [point.avg] : [])
  return values
}

function MetricCard({ serverId, range, config, latest }: { serverId: number | string | undefined; range: MetricRange; config: (typeof METRICS)[number]; latest?: LatestMetric }) {
  const query = useServerMetrics(serverId, config.type, range)
  const values = metricValues(query.data?.points ?? [])
  const current = latest?.value ?? values.at(-1) ?? null
  const previous = values.at(-2) ?? null
  const change = current != null && previous != null ? current - previous : null
  const warning = query.data?.thresholds.warning
  const average = values.length ? values.reduce((sum, value) => sum + value, 0) / values.length : null
  const peak = values.length ? Math.max(...values) : null
  const percent = Math.min(100, Math.max(0, current ?? 0))
  const circumference = 2 * Math.PI * 48
  const Icon = config.icon

  return (
    <article className="overflow-hidden rounded-2xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-3">
          <div className={cn('grid size-10 place-items-center rounded-xl', config.tone)}><Icon className="size-5" /></div>
          <div><h3 className="text-sm font-semibold">{config.title}</h3><p className="text-xs text-muted-foreground">{config.subtitle}</p></div>
        </div>
        <span className="flex items-center gap-1.5 text-[11px] text-muted-foreground"><span className="size-2 rounded-full bg-emerald-500 shadow-[0_0_0_3px_rgba(16,185,129,.12)]" />Live</span>
      </div>

      <div className="mt-5 flex items-end justify-between">
        <div>
          <p className="text-3xl font-bold tracking-tight tabular-nums">{current == null ? '—' : formatNumber(current, { maximumFractionDigits: 1 })}<span className="ml-1 text-base font-medium text-muted-foreground">%</span></p>
          <p className="mt-1 text-[11px] text-muted-foreground">{latest?.recorded_at ? `Updated ${formatRelative(latest.recorded_at)}` : 'Waiting for samples'}</p>
        </div>
        {change != null ? <span className={cn('flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium', change > 0 ? 'bg-amber-500/10 text-amber-600' : 'bg-emerald-500/10 text-emerald-600')}>{change > 0 ? <TrendingUp className="size-3" /> : <TrendingDown className="size-3" />}{formatNumber(Math.abs(change), { maximumFractionDigits: 1 })}%</span> : null}
      </div>

      <div className="mt-5 min-h-40 rounded-xl border bg-muted/15 p-4">
        {query.isLoading ? <Skeleton className="h-32 w-full rounded-xl" /> : query.error ? <div className="grid h-32 place-items-center rounded-xl border border-dashed text-xs text-destructive">Could not load metrics</div> : !values.length ? <div className="grid h-32 place-items-center rounded-xl border border-dashed text-xs text-muted-foreground">Summary appears after the next samples</div> : (
          <div className="flex items-center gap-5">
            <div className="relative size-32 shrink-0">
              <svg viewBox="0 0 120 120" className="size-full -rotate-90" role="img" aria-label={`${config.title}: ${percent}%`}>
                <circle cx="60" cy="60" r="48" fill="none" stroke="currentColor" strokeWidth="10" className="text-muted/70" />
                <circle cx="60" cy="60" r="48" fill="none" stroke={config.color} strokeWidth="10" strokeLinecap="round" strokeDasharray={circumference} strokeDashoffset={circumference * (1 - percent / 100)} className="transition-all duration-700" />
              </svg>
              <div className="absolute inset-0 grid place-items-center text-center"><div><p className="text-2xl font-bold tabular-nums">{formatNumber(percent, { maximumFractionDigits: 0 })}%</p><p className="text-[10px] uppercase tracking-wider text-muted-foreground">current</p></div></div>
            </div>
            <div className="min-w-0 flex-1 space-y-3">
              <div className="flex items-center justify-between border-b pb-2"><span className="text-xs text-muted-foreground">Average</span><span className="text-sm font-semibold tabular-nums">{formatNumber(average, { maximumFractionDigits: 1 })}%</span></div>
              <div className="flex items-center justify-between border-b pb-2"><span className="text-xs text-muted-foreground">Peak</span><span className="text-sm font-semibold tabular-nums">{formatNumber(peak, { maximumFractionDigits: 1 })}%</span></div>
              <div className="flex items-center justify-between"><span className="text-xs text-muted-foreground">Warning</span><span className="text-sm font-semibold tabular-nums text-amber-600">{formatNumber(warning)}%</span></div>
            </div>
          </div>
        )}
      </div>
      <div className="mt-3 flex justify-between text-[11px] text-muted-foreground"><span>{range} summary</span><span>{values.length} samples</span></div>
    </article>
  )
}

export function OverviewMetrics({ serverId }: { serverId: number | string | undefined }) {
  const [range, setRange] = React.useState<MetricRange>('24h')
  const latest = useLatestMetrics(serverId)
  const byType = new Map((latest.data ?? []).map((metric) => [metric.type.value, metric]))
  return (
    <section className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3"><div className="grid size-9 place-items-center rounded-xl bg-indigo-500/10 text-indigo-600"><Activity className="size-4" /></div><div><h2 className="text-base font-semibold">Live performance</h2><p className="text-xs text-muted-foreground">Resource utilization and recent trends</p></div></div>
        <div className="flex w-fit rounded-lg border bg-muted/30 p-1">{RANGES.map((option) => <Button key={option} type="button" size="sm" variant="ghost" className={cn('h-7 px-3 text-xs', range === option && 'bg-background shadow-sm')} onClick={() => setRange(option)}>{option}</Button>)}</div>
      </div>
      <div className="grid gap-4 lg:grid-cols-3">{METRICS.map((config) => <MetricCard key={config.type} serverId={serverId} range={range} config={config} latest={byType.get(config.type)} />)}</div>
    </section>
  )
}

export default OverviewMetrics
