import * as React from 'react'
import { Check, Copy, Terminal } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useAgentInstructions } from '@/hooks/use-servers'
import type { EnumOption } from '@/types/api'

export interface AgentInstallInstructionsProps {
  serverId: number | string | undefined
  /** The server's OS, used only for the heading — content comes from the API. */
  os?: EnumOption | null
}

/**
 * OS-appropriate install steps for the collector agent.
 *
 * Everything rendered comes from the API. If the endpoint is not deployed yet the
 * hook resolves to `null` and the card degrades to a short explanatory note
 * rather than an error.
 */
export function AgentInstallInstructions({
  serverId,
  os,
}: AgentInstallInstructionsProps) {
  const query = useAgentInstructions(serverId)
  const [copied, setCopied] = React.useState(false)

  React.useEffect(() => {
    if (!copied) {
      return
    }

    const timer = window.setTimeout(() => setCopied(false), 2000)

    return () => window.clearTimeout(timer)
  }, [copied])

  const data = query.data
  const snippet =
    data?.script ?? data?.command ?? data?.install_command ?? null
  const steps = data?.steps ?? []

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-3">
        <div>
          <CardTitle className="text-base">Install the agent</CardTitle>
          <p className="mt-1 text-xs text-muted-foreground">
            {data?.label ?? os?.label ?? 'Platform-specific setup steps'}
          </p>
        </div>
        <Terminal className="size-5 shrink-0 text-muted-foreground" aria-hidden />
      </CardHeader>

      <CardContent className="space-y-4">
        {query.isLoading ? (
          <Skeleton className="h-32 w-full" />
        ) : !data ? (
          <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
            Install instructions are not available from the API yet. Issue an agent
            token above and point the collector at this installation&apos;s API
            endpoint.
          </p>
        ) : (
          <>
            {steps.length > 0 ? (
              <ol className="list-decimal space-y-1.5 pl-5 text-sm">
                {steps.map((step, index) => (
                  <li key={`${index}-${step.slice(0, 24)}`}>{step}</li>
                ))}
              </ol>
            ) : null}

            {snippet ? (
              <div className="space-y-2">
                <pre className="overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs leading-relaxed text-slate-100">
                  <code>{snippet}</code>
                </pre>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    void navigator.clipboard
                      ?.writeText(snippet)
                      .then(() => setCopied(true))
                  }}
                >
                  {copied ? (
                    <Check className="size-4" aria-hidden />
                  ) : (
                    <Copy className="size-4" aria-hidden />
                  )}
                  {copied ? 'Copied' : 'Copy command'}
                </Button>
              </div>
            ) : null}

            <dl className="grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
              {data.config_path ? (
                <div>
                  <dt className="font-medium text-foreground">Config path</dt>
                  <dd className="break-all">{data.config_path}</dd>
                </div>
              ) : null}
              {data.endpoint ? (
                <div>
                  <dt className="font-medium text-foreground">Endpoint</dt>
                  <dd className="break-all">{data.endpoint}</dd>
                </div>
              ) : null}
            </dl>

            {data.notes ? (
              <p className="text-xs text-muted-foreground">{data.notes}</p>
            ) : null}
          </>
        )}
      </CardContent>
    </Card>
  )
}

export default AgentInstallInstructions
