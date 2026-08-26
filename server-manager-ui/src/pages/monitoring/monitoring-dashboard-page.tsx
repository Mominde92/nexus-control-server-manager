import { Activity, ArrowRight, ShieldCheck } from 'lucide-react'
import { Link } from 'react-router-dom'

import { HealthBadge } from '@/components/monitoring/health-badge'
import { ServerHealthSummary } from '@/components/monitoring/server-health-summary'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import {
  useMonitoringSummary,
  useServersNeedingAttention,
} from '@/hooks/use-monitoring'
import { useServerAlerts } from '@/hooks/use-server-alerts'
import { EMPTY, formatNumber, formatRelative } from '@/lib/format'

/**
 * Fleet overview.
 *
 * Costs exactly three requests regardless of fleet size: the summary aggregate,
 * the attention list and one page of open alerts. No metric series are fetched.
 */
export function MonitoringDashboardPage() {
  const summary = useMonitoringSummary()
  const attention = useServersNeedingAttention()
  const alerts = useServerAlerts({ status: 'open', per_page: 8, sort: 'started_at' })

  const attentionRows = attention.data ?? []
  const alertRows = alerts.data?.data ?? []

  return (
    <div className="mx-auto max-w-[1600px] space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <div className="mb-2 flex items-center gap-2 text-xs font-semibold text-indigo-600">
            <Activity className="size-3.5" />
            LIVE FLEET MONITORING
          </div>
          <h1 className="text-2xl font-bold">Monitoring</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Health, heartbeats and open alerts across every monitored server.
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" asChild>
            <Link to="/server-alerts">All alerts</Link>
          </Button>
          <Button asChild>
            <Link to="/servers">View servers</Link>
          </Button>
        </div>
      </div>

      {summary.error ? (
        <div className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
          Monitoring summary is unavailable right now.
        </div>
      ) : (
        <ServerHealthSummary
          summary={summary.data}
          isLoading={summary.isLoading}
        />
      )}

      <div className="grid gap-5 xl:grid-cols-2">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <div>
              <CardTitle className="text-base">Needs attention</CardTitle>
              <p className="mt-1 text-xs text-muted-foreground">
                Worst first, as ranked by the API.
              </p>
            </div>
            <ShieldCheck className="size-5 text-muted-foreground" aria-hidden />
          </CardHeader>
          <CardContent>
            {attention.isLoading ? (
              <div className="space-y-2">
                <Skeleton className="h-14 w-full" />
                <Skeleton className="h-14 w-full" />
              </div>
            ) : attentionRows.length === 0 ? (
              <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                Every monitored server is reporting healthy.
              </p>
            ) : (
              <ul className="space-y-2">
                {attentionRows.map((server) => (
                  <li key={server.id}>
                    <Link
                      to={`/servers/${server.id}`}
                      className="flex items-center justify-between gap-3 rounded-xl border p-3 transition hover:bg-muted/50"
                    >
                      <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">{server.name}</p>
                        <p className="truncate text-xs text-muted-foreground">
                          heartbeat {formatRelative(server.last_heartbeat_at)}
                          {server.open_alert_count
                            ? ` · ${formatNumber(server.open_alert_count)} open alerts`
                            : ''}
                        </p>
                      </div>
                      <div className="flex shrink-0 items-center gap-2">
                        <HealthBadge health={server.health} />
                        <ArrowRight className="size-4 text-muted-foreground" />
                      </div>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <div>
              <CardTitle className="text-base">Recent open alerts</CardTitle>
              <p className="mt-1 text-xs text-muted-foreground">
                {alerts.data
                  ? `${formatNumber(alerts.data.meta.total)} open in total`
                  : 'Loading…'}
              </p>
            </div>
            <Activity className="size-5 text-muted-foreground" aria-hidden />
          </CardHeader>
          <CardContent>
            {alerts.isLoading ? (
              <div className="space-y-2">
                <Skeleton className="h-14 w-full" />
                <Skeleton className="h-14 w-full" />
              </div>
            ) : alertRows.length === 0 ? (
              <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                No open alerts.
              </p>
            ) : (
              <ul className="space-y-2">
                {alertRows.map((alert) => (
                  <li
                    key={alert.id}
                    className="flex items-start justify-between gap-3 rounded-xl border p-3"
                  >
                    <div className="min-w-0">
                      <p className="truncate text-sm font-semibold">
                        {alert.title ?? alert.type ?? EMPTY}
                      </p>
                      <p className="truncate text-xs text-muted-foreground">
                        {alert.server ? (
                          <Link
                            to={`/servers/${alert.server.id}`}
                            className="text-indigo-600 hover:underline"
                          >
                            {alert.server.name}
                          </Link>
                        ) : (
                          EMPTY
                        )}
                        {' · '}
                        {formatRelative(alert.started_at)}
                      </p>
                    </div>
                    {alert.severity ? (
                      <Badge variant={alert.severity.variant} className="shrink-0">
                        {alert.severity.label}
                      </Badge>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

export default MonitoringDashboardPage
