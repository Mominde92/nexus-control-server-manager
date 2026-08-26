import * as React from 'react'
import { Check, Copy, KeyRound, RefreshCw, ShieldAlert, Trash2, X } from 'lucide-react'

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
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import {
  useAgentCredentials,
  useIssueCredential,
  useRevokeCredential,
  useRotateCredential,
} from '@/hooks/use-servers'
import { formatDateTime, formatRelative } from '@/lib/format'
import type { AgentCredential } from '@/types/models'

export interface AgentCredentialPanelProps {
  serverId: number | string | undefined
  /** When false the panel renders read-only (no issue/rotate/revoke). */
  canManage?: boolean
}

type PendingAction =
  | { kind: 'rotate' }
  | { kind: 'revoke'; credential: AgentCredential }
  | null

/** Copy-to-clipboard button that reports success without holding the value. */
function CopyButton({ value, label }: { value: string; label: string }) {
  const [copied, setCopied] = React.useState(false)

  React.useEffect(() => {
    if (!copied) {
      return
    }

    const timer = window.setTimeout(() => setCopied(false), 2000)

    return () => window.clearTimeout(timer)
  }, [copied])

  return (
    <Button
      type="button"
      size="sm"
      variant="outline"
      onClick={() => {
        void navigator.clipboard?.writeText(value).then(() => setCopied(true))
      }}
    >
      {copied ? <Check className="size-4" aria-hidden /> : <Copy className="size-4" aria-hidden />}
      {copied ? 'Copied' : label}
    </Button>
  )
}

/**
 * Agent credentials for one server.
 *
 * The plaintext token exists only in this component's transient state: it is
 * shown once in a dismissible callout, cleared on dismiss, and never written to
 * the query cache, localStorage or any store that outlives the view.
 */
export function AgentCredentialPanel({
  serverId,
  canManage = false,
}: AgentCredentialPanelProps) {
  const credentials = useAgentCredentials(serverId)
  const issue = useIssueCredential(serverId)
  const rotate = useRotateCredential(serverId)
  const revoke = useRevokeCredential(serverId)

  const [name, setName] = React.useState('')
  const [pending, setPending] = React.useState<PendingAction>(null)
  // One-time plaintext. Never persisted anywhere.
  const [issuedToken, setIssuedToken] = React.useState<string | null>(null)

  const rows = credentials.data ?? []
  const busy = issue.isPending || rotate.isPending || revoke.isPending

  async function handleIssue() {
    const result = await issue.mutateAsync(
      name.trim().length > 0 ? { name: name.trim() } : undefined,
    )
    setName('')
    setIssuedToken(result.token)
  }

  async function handleRotate() {
    const result = await rotate.mutateAsync()
    setIssuedToken(result.token)
  }

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-3">
        <div>
          <CardTitle className="text-base">Agent credentials</CardTitle>
          <p className="mt-1 text-xs text-muted-foreground">
            Tokens the collector agent uses to post heartbeats and metrics.
          </p>
        </div>
        <KeyRound className="size-5 shrink-0 text-muted-foreground" aria-hidden />
      </CardHeader>

      <CardContent className="space-y-5">
        {issuedToken ? (
          <div className="rounded-xl border border-amber-400/60 bg-amber-50 p-4 dark:bg-amber-500/10">
            <div className="flex items-start gap-3">
              <ShieldAlert className="mt-0.5 size-5 shrink-0 text-amber-600" aria-hidden />
              <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-amber-900 dark:text-amber-200">
                  Copy this token now — it will never be shown again.
                </p>
                <p className="mt-1 text-xs text-amber-800 dark:text-amber-300/90">
                  Store it in the agent&apos;s configuration. If you lose it, rotate
                  the credential to issue a replacement.
                </p>
                <code className="mt-3 block overflow-x-auto rounded-lg bg-white/70 p-3 font-mono text-xs break-all dark:bg-slate-950/60">
                  {issuedToken}
                </code>
                <div className="mt-3 flex gap-2">
                  <CopyButton value={issuedToken} label="Copy token" />
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => setIssuedToken(null)}
                  >
                    <X className="size-4" aria-hidden />
                    Dismiss
                  </Button>
                </div>
              </div>
            </div>
          </div>
        ) : null}

        {canManage ? (
          <div className="flex flex-col gap-2 sm:flex-row">
            <Input
              value={name}
              placeholder="Credential label (optional)"
              onChange={(event) => setName(event.target.value)}
            />
            <div className="flex gap-2">
              <Button type="button" disabled={busy} onClick={() => void handleIssue()}>
                <KeyRound className="size-4" aria-hidden />
                Issue token
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={busy || rows.length === 0}
                onClick={() => setPending({ kind: 'rotate' })}
              >
                <RefreshCw className="size-4" aria-hidden />
                Rotate
              </Button>
            </div>
          </div>
        ) : null}

        {credentials.isLoading ? (
          <div className="space-y-2">
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-14 w-full" />
          </div>
        ) : credentials.error ? (
          <p className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
            {credentials.error.message}
          </p>
        ) : rows.length === 0 ? (
          <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
            No agent credentials issued yet.
          </p>
        ) : (
          <ul className="space-y-2">
            {rows.map((credential) => (
              <li
                key={credential.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3"
              >
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <p className="truncate text-sm font-semibold">
                      {credential.name ?? `Credential #${credential.id}`}
                    </p>
                    <Badge variant={credential.is_usable ? 'success' : 'secondary'}>
                      {credential.is_usable ? 'Active' : 'Revoked'}
                    </Badge>
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">
                    {credential.token_hint ? `${credential.token_hint} · ` : ''}
                    last used {formatRelative(credential.last_used_at)}
                    {credential.expires_at
                      ? ` · expires ${formatDateTime(credential.expires_at)}`
                      : ''}
                  </p>
                </div>

                {canManage && credential.is_usable ? (
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="text-destructive"
                    disabled={busy}
                    onClick={() => setPending({ kind: 'revoke', credential })}
                  >
                    <Trash2 className="size-4" aria-hidden />
                    Revoke
                  </Button>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </CardContent>

      <AlertDialog
        open={pending !== null}
        onOpenChange={(open) => {
          if (!open) {
            setPending(null)
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {pending?.kind === 'rotate'
                ? 'Rotate the agent token?'
                : 'Revoke this credential?'}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {pending?.kind === 'rotate'
                ? 'A new token is issued and shown once. The agent stops reporting until it is updated with the new token.'
                : 'The agent using this credential will stop reporting immediately. This cannot be undone.'}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                const action = pending
                setPending(null)

                if (action?.kind === 'rotate') {
                  void handleRotate()
                } else if (action?.kind === 'revoke') {
                  void revoke.mutateAsync(action.credential.id)
                }
              }}
            >
              {pending?.kind === 'rotate' ? 'Rotate token' : 'Revoke'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  )
}

export default AgentCredentialPanel
