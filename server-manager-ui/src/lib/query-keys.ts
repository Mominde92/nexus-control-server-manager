import type { ListParams } from '@/types/api'

/**
 * Stable React Query key factories.
 *
 * Every resource exposes `all` (invalidate everything for it), `lists()`
 * (invalidate every paginated view regardless of params), `list(params)` and
 * `detail(id)`. Keeping them here means an invalidation can never drift out of
 * sync with the key a hook actually used.
 */
function resourceKeys(resource: string) {
  const all = [resource] as const

  return {
    all,
    lists: () => [...all, 'list'] as const,
    list: (params?: ListParams) => [...all, 'list', params ?? {}] as const,
    details: () => [...all, 'detail'] as const,
    detail: (id: number | string) => [...all, 'detail', String(id)] as const,
    credentials: (id: number | string) =>
      [...all, 'credentials', String(id)] as const,
  }
}

export const queryKeys = {
  auth: {
    all: ['auth'] as const,
    me: () => ['auth', 'me'] as const,
  },
  meta: {
    all: ['meta'] as const,
    options: () => ['meta', 'options'] as const,
  },
  dashboard: {
    all: ['dashboard'] as const,
    stats: () => ['dashboard', 'stats'] as const,
    expiringHostings: (params?: ListParams) =>
      ['dashboard', 'expiring-hostings', params ?? {}] as const,
    expiringDomains: (params?: ListParams) =>
      ['dashboard', 'expiring-domains', params ?? {}] as const,
    recentActivities: (params?: ListParams) =>
      ['dashboard', 'recent-activities', params ?? {}] as const,
  },
  hostings: resourceKeys('hostings'),
  domains: resourceKeys('domains'),
  providers: resourceKeys('providers'),
  clients: resourceKeys('clients'),
  projects: resourceKeys('projects'),
  users: resourceKeys('users'),
  activityLogs: resourceKeys('activity-logs'),
} as const

/** Look up a resource key factory by its API path segment. */
export function resourceQueryKeys(resource: string) {
  const map: Record<string, ReturnType<typeof resourceKeys>> = {
    hostings: queryKeys.hostings,
    domains: queryKeys.domains,
    providers: queryKeys.providers,
    clients: queryKeys.clients,
    projects: queryKeys.projects,
    users: queryKeys.users,
    'activity-logs': queryKeys.activityLogs,
  }

  return map[resource] ?? resourceKeys(resource)
}

export default queryKeys
