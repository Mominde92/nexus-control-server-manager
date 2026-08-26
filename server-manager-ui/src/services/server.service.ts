import apiClient, { unwrap } from '@/lib/api-client'
import { createCrudService, type RequestOptions } from '@/services/crud.service'
import type { ApiEnvelope } from '@/types/api'
import type {
  AgentCredential,
  AgentCredentialPayload,
  AgentInstructions,
  IssuedAgentToken,
  LatestMetric,
  MetricRange,
  MetricSeries,
  Server,
  ServerPayload,
} from '@/types/models'

const crud = createCrudService<Server, ServerPayload>('servers')

function emit(options: RequestOptions | undefined, message: string): void {
  options?.onMessage?.(message)
}

export interface MetricsQuery {
  type: string
  range: MetricRange | string
}

/** The API takes a window in hours, so UI ranges are translated on the way out. */
const RANGE_HOURS: Record<string, number> = {
  '1h': 1,
  '6h': 6,
  '24h': 24,
  '7d': 24 * 7,
  '30d': 24 * 30,
  '90d': 24 * 90,
}

/** Raw series envelope: the descriptor is nested under `metric`. */
interface RawMetricSeries {
  metric?: {
    value?: string
    label?: string
    unit?: string | null
    is_percentage?: boolean
  }
  source?: string | null
  bucket?: string | null
  truncated?: boolean
  thresholds?: { warning?: number | null; critical?: number | null }
  points?: MetricSeries['points']
}

/** Raw latest-metrics envelope: `{server_id, metrics: [...]}` with flat types. */
interface RawLatestMetric {
  type?: string
  label?: string
  unit?: string | null
  value?: number | null
  recorded_at?: string | null
}

/**
 * Servers: the shared CRUD factory plus the monitoring-only endpoints.
 *
 * Metric reads are always scoped to one server and one type+range, so no screen
 * can accidentally pull every sample for every machine.
 */
export const serverService = {
  ...crud,

  async enableMonitoring(
    id: number | string,
    options?: RequestOptions,
  ): Promise<Server> {
    const response = await apiClient.post<ApiEnvelope<Server>>(
      `/servers/${id}/monitoring/enable`,
    )
    emit(options, response.data.message)

    return unwrap(response)
  },

  async disableMonitoring(
    id: number | string,
    options?: RequestOptions,
  ): Promise<Server> {
    const response = await apiClient.post<ApiEnvelope<Server>>(
      `/servers/${id}/monitoring/disable`,
    )
    emit(options, response.data.message)

    return unwrap(response)
  },

  /** Stored credentials only — never a plaintext token. */
  async credentials(id: number | string): Promise<AgentCredential[]> {
    return unwrap(
      await apiClient.get<ApiEnvelope<AgentCredential[]>>(
        `/servers/${id}/agent-credentials`,
      ),
    )
  },

  /** Issues a credential; the returned plaintext token is shown exactly once. */
  async issueCredential(
    id: number | string,
    payload?: AgentCredentialPayload,
    options?: RequestOptions,
  ): Promise<IssuedAgentToken> {
    const response = await apiClient.post<ApiEnvelope<IssuedAgentToken>>(
      `/servers/${id}/agent-credentials`,
      payload ?? {},
    )
    emit(options, response.data.message)

    return unwrap(response)
  },

  async rotateCredential(
    id: number | string,
    options?: RequestOptions,
  ): Promise<IssuedAgentToken> {
    const response = await apiClient.post<ApiEnvelope<IssuedAgentToken>>(
      `/servers/${id}/agent-credentials/rotate`,
    )
    emit(options, response.data.message)

    return unwrap(response)
  },

  async revokeCredential(
    id: number | string,
    credentialId: number | string,
    options?: RequestOptions,
  ): Promise<null> {
    const response = await apiClient.delete<ApiEnvelope<null>>(
      `/servers/${id}/agent-credentials/${credentialId}`,
    )
    emit(options, response.data.message)

    return null
  },

  /**
   * OS-specific install steps.
   *
   * The endpoint moved twice during development, so each known path is tried in
   * turn and a 404 falls through to the next. A 404 from the last one propagates
   * so the caller can degrade gracefully.
   */
  async instructions(id: number | string): Promise<AgentInstructions> {
    const paths = [
      `/servers/${id}/agent-instructions`,
      `/servers/${id}/agent-credentials/instructions`,
      `/servers/${id}/agent/instructions`,
    ]

    let lastError: unknown

    for (const path of paths) {
      try {
        return unwrap(await apiClient.get<ApiEnvelope<AgentInstructions>>(path))
      } catch (cause) {
        if ((cause as { status?: number }).status !== 404) {
          throw cause
        }

        lastError = cause
      }
    }

    throw lastError
  },

  /**
   * One metric type, one range, one server — flattened into `MetricSeries`.
   *
   * The UI range label is translated to the `hours` window the API expects, and
   * both the documented `type`/`range` and the shipped `metric`/`hours` params
   * are sent so either server build answers correctly.
   */
  async metrics(
    id: number | string,
    query: MetricsQuery,
  ): Promise<MetricSeries> {
    const hours = RANGE_HOURS[query.range] ?? 24
    const raw = unwrap(
      await apiClient.get<ApiEnvelope<RawMetricSeries>>(`/servers/${id}/metrics`, {
        params: {
          metric: query.type,
          hours,
          type: query.type,
          range: query.range,
        },
      }),
    )

    return {
      type: raw.metric?.value ?? query.type,
      label: raw.metric?.label ?? null,
      unit: raw.metric?.unit ?? null,
      is_percentage: raw.metric?.is_percentage ?? false,
      source: raw.source ?? null,
      bucket: raw.bucket ?? null,
      truncated: raw.truncated ?? false,
      thresholds: {
        warning: raw.thresholds?.warning ?? null,
        critical: raw.thresholds?.critical ?? null,
      },
      points: raw.points ?? [],
    }
  },

  /** Latest sample per metric type. Accepts the wrapped or bare array shape. */
  async latestMetrics(id: number | string): Promise<LatestMetric[]> {
    const raw = unwrap(
      await apiClient.get<
        ApiEnvelope<{ metrics?: RawLatestMetric[] } | RawLatestMetric[]>
      >(`/servers/${id}/metrics/latest`),
    )

    const rows = Array.isArray(raw) ? raw : (raw?.metrics ?? [])

    return rows.map((row) => ({
      type: { value: row.type ?? '', label: row.label ?? row.type ?? '' },
      value: row.value ?? null,
      unit: row.unit ?? null,
      recorded_at: row.recorded_at ?? null,
    }))
  },
}

export default serverService
