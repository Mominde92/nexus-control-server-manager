import * as React from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Save } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { useNavigate, useParams } from 'react-router-dom'
import { z } from 'zod'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { useMetaOptionList } from '@/hooks/use-meta'
import { useCreateServer, useServer, useUpdateServer } from '@/hooks/use-servers'
import apiClient, { unwrapList } from '@/lib/api-client'
import type { ApiError } from '@/types/api'
import type { ServerPayload } from '@/types/models'

/** Optional positive integer coming out of a text input. */
const optionalNumber = z
  .union([z.string(), z.number(), z.null()])
  .transform((value) =>
    value === null || value === '' ? null : Number(value),
  )
  .refine((value) => value === null || Number.isFinite(value), {
    message: 'Enter a number.',
  })

const optionalText = z
  .string()
  .trim()
  .max(255, 'Keep this under 255 characters.')
  .optional()

const schema = z
  .object({
    name: z.string().trim().min(1, 'A server name is required.').max(255),
    hostname: optionalText,
    ip_address: optionalText,
    os: z.string().optional(),
    os_version: optionalText,
    hosting_id: z.string().optional(),
    client_id: z.string().optional(),
    project_id: z.string().optional(),
    monitoring_enabled: z.boolean(),
    heartbeat_interval_seconds: optionalNumber,
    heartbeat_grace_seconds: optionalNumber,
    cpu_warning_threshold: optionalNumber,
    cpu_critical_threshold: optionalNumber,
    memory_warning_threshold: optionalNumber,
    memory_critical_threshold: optionalNumber,
    disk_warning_threshold: optionalNumber,
    disk_critical_threshold: optionalNumber,
    notes: z.string().trim().max(5000).optional(),
  })
  // Critical must never sit below warning, mirroring the API's own rule.
  .superRefine((values, context) => {
    const pairs = [
      ['cpu', values.cpu_warning_threshold, values.cpu_critical_threshold],
      ['memory', values.memory_warning_threshold, values.memory_critical_threshold],
      ['disk', values.disk_warning_threshold, values.disk_critical_threshold],
    ] as const

    for (const [group, warning, critical] of pairs) {
      if (warning !== null && critical !== null && critical < warning) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: [`${group}_critical_threshold`],
          message: 'Critical must be greater than or equal to warning.',
        })
      }
    }
  })

type FormValues = z.input<typeof schema>
type ParsedValues = z.output<typeof schema>

const THRESHOLD_FIELDS = [
  ['cpu_warning_threshold', 'CPU warning %'],
  ['cpu_critical_threshold', 'CPU critical %'],
  ['memory_warning_threshold', 'Memory warning %'],
  ['memory_critical_threshold', 'Memory critical %'],
  ['disk_warning_threshold', 'Disk warning %'],
  ['disk_critical_threshold', 'Disk critical %'],
] as const

/** Relation options are fetched from their own list endpoints, never hardcoded. */
function useRelationOptions(resource: string) {
  return useQuery({
    queryKey: ['form-relations', resource],
    queryFn: async () => {
      const result = await unwrapList<{ id: number; name?: string; title?: string }>(
        await apiClient.get(`/${resource}`, {
          params: { per_page: 100, sort: 'name', direction: 'asc' },
        }),
      )

      return result.data.map((row) => ({
        id: row.id,
        name: row.name ?? row.title ?? `#${row.id}`,
      }))
    },
    staleTime: 5 * 60_000,
  })
}

function text(value: unknown): string {
  return value === null || value === undefined ? '' : String(value)
}

export function ServerFormPage({ mode }: { mode: 'create' | 'edit' }) {
  const { id } = useParams()
  const navigate = useNavigate()
  const detail = useServer(mode === 'edit' ? id : undefined)
  const create = useCreateServer()
  const update = useUpdateServer()
  const osOptions = useMetaOptionList('server_os')
  const hostings = useRelationOptions('hostings')
  const clients = useRelationOptions('clients')
  const projects = useRelationOptions('projects')

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: '',
      monitoring_enabled: true,
    },
  })

  const record = detail.data

  // Hydrate once the record arrives; every value is server-provided.
  React.useEffect(() => {
    if (!record) {
      return
    }

    form.reset({
      name: record.name,
      hostname: text(record.hostname),
      ip_address: text(record.ip_address),
      os: record.os?.value ?? '',
      os_version: text(record.os_version),
      hosting_id: text(record.hosting?.id ?? record.hosting_id),
      client_id: text(record.client?.id ?? record.client_id),
      project_id: text(record.project?.id ?? record.project_id),
      monitoring_enabled: record.monitoring_enabled,
      heartbeat_interval_seconds: text(record.heartbeat_interval_seconds),
      heartbeat_grace_seconds: text(record.heartbeat_grace_seconds),
      cpu_warning_threshold: text(record.cpu_warning_threshold),
      cpu_critical_threshold: text(record.cpu_critical_threshold),
      memory_warning_threshold: text(record.memory_warning_threshold),
      memory_critical_threshold: text(record.memory_critical_threshold),
      disk_warning_threshold: text(record.disk_warning_threshold),
      disk_critical_threshold: text(record.disk_critical_threshold),
      notes: text(record.notes),
    })
  }, [record, form])

  function toPayload(values: ParsedValues): ServerPayload {
    const relation = (value: string | undefined) =>
      value && value !== '' ? Number(value) : null

    return {
      name: values.name,
      hostname: values.hostname || null,
      ip_address: values.ip_address || null,
      os: values.os || null,
      os_version: values.os_version || null,
      hosting_id: relation(values.hosting_id),
      client_id: relation(values.client_id),
      project_id: relation(values.project_id),
      monitoring_enabled: values.monitoring_enabled,
      heartbeat_interval_seconds: values.heartbeat_interval_seconds,
      heartbeat_grace_seconds: values.heartbeat_grace_seconds,
      cpu_warning_threshold: values.cpu_warning_threshold,
      cpu_critical_threshold: values.cpu_critical_threshold,
      memory_warning_threshold: values.memory_warning_threshold,
      memory_critical_threshold: values.memory_critical_threshold,
      disk_warning_threshold: values.disk_warning_threshold,
      disk_critical_threshold: values.disk_critical_threshold,
      notes: values.notes || null,
    }
  }

  /** Map an API 422 onto the matching form fields. */
  function applyApiErrors(error: ApiError) {
    for (const [field, messages] of Object.entries(error.errors ?? {})) {
      form.setError(field as keyof FormValues, {
        type: 'server',
        message: messages[0] ?? 'This value was rejected.',
      })
    }
  }

  const submit = form.handleSubmit(async (values) => {
    const payload = toPayload(values as ParsedValues)

    try {
      if (mode === 'edit' && id) {
        const saved = await update.mutateAsync({ id, payload })
        navigate(`/servers/${saved.id}`)
      } else {
        const saved = await create.mutateAsync(payload)
        navigate(`/servers/${saved.id}`)
      }
    } catch (cause) {
      applyApiErrors(cause as ApiError)
    }
  })

  if (mode === 'edit' && detail.isLoading) {
    return <Skeleton className="h-96 w-full" />
  }

  const errors = form.formState.errors
  const busy = create.isPending || update.isPending

  function fieldError(name: keyof FormValues) {
    const message = errors[name]?.message

    return message ? (
      <p className="mt-1 text-xs text-destructive">{String(message)}</p>
    ) : null
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <div className="flex items-center gap-4">
        <Button variant="outline" size="icon" onClick={() => navigate('/servers')}>
          <ArrowLeft />
        </Button>
        <div>
          <p className="text-xs font-semibold tracking-wider text-indigo-600 uppercase">
            Monitoring
          </p>
          <h1 className="text-2xl font-bold">
            {mode === 'create' ? 'Add server' : 'Edit server'}
          </h1>
        </div>
      </div>

      <form onSubmit={submit} className="space-y-5">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Identity</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="name">Name *</Label>
              <Input id="name" className="mt-1.5" {...form.register('name')} />
              {fieldError('name')}
            </div>
            <div>
              <Label htmlFor="hostname">Hostname</Label>
              <Input id="hostname" className="mt-1.5" {...form.register('hostname')} />
              {fieldError('hostname')}
            </div>
            <div>
              <Label htmlFor="ip_address">IP address</Label>
              <Input
                id="ip_address"
                className="mt-1.5"
                {...form.register('ip_address')}
              />
              {fieldError('ip_address')}
            </div>
            <div>
              <Label htmlFor="os">Operating system</Label>
              <select
                id="os"
                className="mt-1.5 h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                {...form.register('os')}
              >
                <option value="">Select…</option>
                {osOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              {fieldError('os')}
            </div>
            <div>
              <Label htmlFor="os_version">OS version</Label>
              <Input
                id="os_version"
                className="mt-1.5"
                {...form.register('os_version')}
              />
              {fieldError('os_version')}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Links</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            {(
              [
                ['hosting_id', 'Hosting', hostings.data],
                ['client_id', 'Client', clients.data],
                ['project_id', 'Project', projects.data],
              ] as const
            ).map(([field, label, options]) => (
              <div key={field}>
                <Label htmlFor={field}>{label}</Label>
                <select
                  id={field}
                  className="mt-1.5 h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                  {...form.register(field)}
                >
                  <option value="">None</option>
                  {(options ?? []).map((option) => (
                    <option key={option.id} value={option.id}>
                      {option.name}
                    </option>
                  ))}
                </select>
                {fieldError(field)}
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Monitoring</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center gap-3">
              <Switch
                id="monitoring_enabled"
                checked={form.watch('monitoring_enabled')}
                onCheckedChange={(checked) =>
                  form.setValue('monitoring_enabled', checked)
                }
              />
              <Label htmlFor="monitoring_enabled">Monitoring enabled</Label>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <Label htmlFor="heartbeat_interval_seconds">
                  Heartbeat interval (s)
                </Label>
                <Input
                  id="heartbeat_interval_seconds"
                  type="number"
                  className="mt-1.5"
                  {...form.register('heartbeat_interval_seconds')}
                />
                {fieldError('heartbeat_interval_seconds')}
              </div>
              <div>
                <Label htmlFor="heartbeat_grace_seconds">Heartbeat grace (s)</Label>
                <Input
                  id="heartbeat_grace_seconds"
                  type="number"
                  className="mt-1.5"
                  {...form.register('heartbeat_grace_seconds')}
                />
                {fieldError('heartbeat_grace_seconds')}
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {THRESHOLD_FIELDS.map(([field, label]) => (
                <div key={field}>
                  <Label htmlFor={field}>{label}</Label>
                  <Input
                    id={field}
                    type="number"
                    className="mt-1.5"
                    {...form.register(field)}
                  />
                  {fieldError(field)}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Notes</CardTitle>
          </CardHeader>
          <CardContent>
            <Textarea className="min-h-28" {...form.register('notes')} />
            {fieldError('notes')}
          </CardContent>
        </Card>

        <div className="flex justify-end gap-3">
          <Button type="button" variant="outline" onClick={() => navigate('/servers')}>
            Cancel
          </Button>
          <Button type="submit" disabled={busy}>
            <Save />
            {mode === 'create' ? 'Create server' : 'Save changes'}
          </Button>
        </div>
      </form>
    </div>
  )
}

export default ServerFormPage
