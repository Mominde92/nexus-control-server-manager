import * as React from 'react'
import { ArrowLeft, Pencil, PlugZap, PowerOff, Trash2 } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router-dom'

import { AgentCredentialPanel } from '@/components/monitoring/agent-credential-panel'
import { AgentInstallInstructions } from '@/components/monitoring/agent-install-instructions'
import { AlertTable } from '@/components/monitoring/alert-table'
import { HealthBadge } from '@/components/monitoring/health-badge'
import { MetricChart } from '@/components/monitoring/metric-chart'
import { OverviewMetrics } from '@/components/monitoring/overview-metrics'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useAuth } from '@/hooks/use-auth'
import { useServerAlerts } from '@/hooks/use-server-alerts'
import {
  useDeleteServer,
  useDisableMonitoring,
  useEnableMonitoring,
  useServer,
} from '@/hooks/use-servers'
import { useListParams } from '@/hooks/use-list-params'
import { EMPTY, formatDateTime, formatNumber, formatRelative } from '@/lib/format'
import type { Server } from '@/types/models'

function Field({
  label,
  children,
}: {
  label: string
  children: React.ReactNode
}) {
  return (
    <div className="rounded-xl bg-muted/40 p-3">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className="mt-1 text-sm font-semibold break-words">{children}</p>
    </div>
  )
}

function RelationLink({
  to,
  name,
}: {
  to: string
  name: string | undefined | null
}) {
  if (!name) {
    return <>{EMPTY}</>
  }

  return (
    <Link to={to} className="text-indigo-600 hover:underline">
      {name}
    </Link>
  )
}

/** Per-server thresholds, keyed by the metric type the chart is showing. */
function thresholdsFor(server: Server | undefined) {
  return (type: string) => {
    if (!server) {
      return {}
    }

    if (type.startsWith('cpu')) {
      return {
        warning: server.cpu_warning_threshold,
        critical: server.cpu_critical_threshold,
      }
    }

    if (type.startsWith('memory')) {
      return {
        warning: server.memory_warning_threshold,
        critical: server.memory_critical_threshold,
      }
    }

    if (type.startsWith('disk')) {
      return {
        warning: server.disk_warning_threshold,
        critical: server.disk_critical_threshold,
      }
    }

    return {}
  }
}

/**
 * One server, in depth.
 *
 * Metrics are only fetched inside the Metrics tab (one type + range at a time)
 * and the alerts tab asks the paginated alert endpoint filtered to this server.
 */
export function ServerDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { can } = useAuth()
  const query = useServer(id)
  const enable = useEnableMonitoring()
  const disable = useDisableMonitoring()
  const remove = useDeleteServer()
  const alertParams = useListParams({ defaultSort: 'started_at' })
  const alerts = useServerAlerts(
    { ...alertParams.params, server_id: id },
    { enabled: Boolean(id) },
  )
  const [confirmDelete, setConfirmDelete] = React.useState(false)

  const server = query.data
  const canManageAgent = can('server.agent.manage')

  if (query.isLoading) {
    return <Skeleton className="h-72 w-full" />
  }

  if (query.error) {
    return (
      <div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-6 text-destructive">
        {query.error.message}
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex items-start gap-4">
          <Button variant="outline" size="icon" onClick={() => navigate('/servers')}>
            <ArrowLeft />
          </Button>
          <div>
            <p className="text-xs font-semibold tracking-wider text-indigo-600 uppercase">
              Server
            </p>
            <h1 className="text-2xl font-bold">{server?.name}</h1>
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <HealthBadge health={server?.health} />
              {server?.os ? (
                <Badge variant={server.os.variant}>{server.os.label}</Badge>
              ) : null}
              <span className="text-xs text-muted-foreground">
                heartbeat {formatRelative(server?.last_heartbeat_at)}
              </span>
              {server?.is_stale ? (
                <Badge variant="destructive">Stale</Badge>
              ) : null}
            </div>
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          {server?.monitoring_enabled ? (
            <Button
              variant="outline"
              disabled={disable.isPending}
              onClick={() => server && disable.mutate(server.id)}
            >
              <PowerOff />
              Disable monitoring
            </Button>
          ) : (
            <Button
              variant="outline"
              disabled={enable.isPending}
              onClick={() => server && enable.mutate(server.id)}
            >
              <PlugZap />
              Enable monitoring
            </Button>
          )}
          <Button variant="outline" onClick={() => navigate(`/servers/${id}/edit`)}>
            <Pencil />
            Edit
          </Button>
          <Button variant="destructive" onClick={() => setConfirmDelete(true)}>
            <Trash2 />
            Delete
          </Button>
        </div>
      </div>

      <Tabs defaultValue="overview">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="metrics">Metrics</TabsTrigger>
          <TabsTrigger value="alerts">Alerts</TabsTrigger>
          <TabsTrigger value="agent">Agent</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="space-y-5 pt-5">
          <OverviewMetrics serverId={id} />

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Identity</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <Field label="Hostname">{server?.hostname ?? EMPTY}</Field>
              <Field label="IP address">{server?.ip_address ?? EMPTY}</Field>
              <Field label="Operating system">
                {server?.os?.label ?? EMPTY}
                {server?.os_version ? ` ${server.os_version}` : ''}
              </Field>
              <Field label="Hosting">
                <RelationLink
                  to={`/hostings/${server?.hosting?.id}`}
                  name={server?.hosting?.name}
                />
              </Field>
              <Field label="Client">
                <RelationLink
                  to={`/clients/${server?.client?.id}`}
                  name={server?.client?.name}
                />
              </Field>
              <Field label="Project">
                <RelationLink
                  to={`/projects/${server?.project?.id}`}
                  name={server?.project?.name}
                />
              </Field>
              {server?.notes ? (
                <div className="sm:col-span-2 lg:col-span-3">
                  <Field label="Notes">{server.notes}</Field>
                </div>
              ) : null}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Heartbeat &amp; agent</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <Field label="Last heartbeat">
                {formatDateTime(server?.last_heartbeat_at)}
              </Field>
              <Field label="Seconds since heartbeat">
                {formatNumber(server?.seconds_since_heartbeat)}
              </Field>
              <Field label="Interval / grace">
                {formatNumber(server?.heartbeat_interval_seconds)} /{' '}
                {formatNumber(server?.heartbeat_grace_seconds)} s
              </Field>
              <Field label="Agent version">{server?.agent_version ?? EMPTY}</Field>
              <Field label="Uptime">
                {server?.uptime_seconds
                  ? `${formatNumber(Math.floor(server.uptime_seconds / 3600))} h`
                  : EMPTY}
              </Field>
              <Field label="Open alerts">
                {formatNumber(server?.open_alert_count ?? 0)}
              </Field>
              <Field label="CPU thresholds">
                {formatNumber(server?.cpu_warning_threshold)} /{' '}
                {formatNumber(server?.cpu_critical_threshold)}
              </Field>
              <Field label="Memory thresholds">
                {formatNumber(server?.memory_warning_threshold)} /{' '}
                {formatNumber(server?.memory_critical_threshold)}
              </Field>
              <Field label="Disk thresholds">
                {formatNumber(server?.disk_warning_threshold)} /{' '}
                {formatNumber(server?.disk_critical_threshold)}
              </Field>
            </CardContent>
          </Card>

        </TabsContent>

        <TabsContent value="metrics" className="pt-5">
          <Card>
            <CardContent className="pt-6">
              <MetricChart serverId={id} thresholds={thresholdsFor(server)} />
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="alerts" className="pt-5">
          <AlertTable
            hideServer
            rows={alerts.data?.data}
            meta={alerts.data?.meta}
            isLoading={alerts.isLoading}
            sort={alertParams.sort}
            direction={alertParams.direction}
            onSort={alertParams.setSort}
            onPageChange={alertParams.setPage}
            onPerPageChange={alertParams.setPerPage}
            emptyMessage="No alerts recorded for this server."
          />
        </TabsContent>

        <TabsContent value="agent" className="space-y-5 pt-5">
          {canManageAgent ? (
            <>
              <AgentCredentialPanel serverId={id} canManage />
              <AgentInstallInstructions serverId={id} os={server?.os} />
            </>
          ) : (
            <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
              You do not have permission to manage this server&apos;s agent
              credentials.
            </p>
          )}
        </TabsContent>
      </Tabs>

      <AlertDialog open={confirmDelete} onOpenChange={setConfirmDelete}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this server?</AlertDialogTitle>
            <AlertDialogDescription>
              The server stops being monitored and disappears from the fleet list.
              Historical metrics and alerts are retained server-side.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                setConfirmDelete(false)

                if (id) {
                  remove.mutate(id, {
                    onSuccess: () => navigate('/servers'),
                  })
                }
              }}
            >
              Delete server
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

export default ServerDetailPage
