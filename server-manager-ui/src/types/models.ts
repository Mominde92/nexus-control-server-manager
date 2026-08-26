import type { EnumOption } from '@/types/api'

/** Minimal shape used whenever the API embeds a related record. */
export interface RelationRef {
  id: number
  name: string
}

/* -------------------------------------------------------------------------- */
/* Auth & users                                                               */
/* -------------------------------------------------------------------------- */

export interface RoleRef {
  value: string
  label: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  phone: string | null
  job_title: string | null
  avatar_path: string | null
  status: EnumOption
  role: RoleRef
  permissions: string[]
  last_login_at: string | null
  created_at: string | null
}

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  job_title: string | null
  avatar_path: string | null
  status: EnumOption
  role: RoleRef | null
  permissions?: string[]
  last_login_at: string | null
  created_at: string | null
  updated_at?: string | null
  deleted_at?: string | null
}

export interface UserPayload {
  name: string
  email: string
  phone?: string | null
  job_title?: string | null
  password?: string
  password_confirmation?: string
  role?: string
  status?: string
}

/* -------------------------------------------------------------------------- */
/* Clients                                                                    */
/* -------------------------------------------------------------------------- */

export interface Client {
  id: number
  name: string
  company_name: string | null
  email: string | null
  phone: string | null
  address: string | null
  notes: string | null
  projects_count?: number
  hostings_count?: number
  domains_count?: number
  created_at: string | null
  updated_at?: string | null
  deleted_at?: string | null
}

export interface ClientPayload {
  name: string
  company_name?: string | null
  email?: string | null
  phone?: string | null
  address?: string | null
  notes?: string | null
}

/* -------------------------------------------------------------------------- */
/* Providers                                                                  */
/* -------------------------------------------------------------------------- */

export interface Provider {
  id: number
  name: string
  type: EnumOption | null
  status: EnumOption | null
  website: string | null
  email: string | null
  phone: string | null
  support_url: string | null
  account_number: string | null
  notes: string | null
  hostings_count?: number
  domains_count?: number
  created_at: string | null
  updated_at?: string | null
  deleted_at?: string | null
}

export interface ProviderPayload {
  name: string
  type?: string | null
  status?: string | null
  website?: string | null
  email?: string | null
  phone?: string | null
  support_url?: string | null
  account_number?: string | null
  notes?: string | null
}

/* -------------------------------------------------------------------------- */
/* Projects                                                                   */
/* -------------------------------------------------------------------------- */

export interface Project {
  id: number
  name: string
  client: RelationRef | null
  client_id?: number | null
  status: EnumOption | null
  description: string | null
  url: string | null
  start_date: string | null
  end_date: string | null
  price: number | null
  currency: string | null
  notes: string | null
  hostings_count?: number
  domains_count?: number
  created_at: string | null
  updated_at?: string | null
  deleted_at?: string | null
}

export interface ProjectPayload {
  name: string
  client_id?: number | null
  status?: string | null
  description?: string | null
  url?: string | null
  start_date?: string | null
  end_date?: string | null
  price?: number | null
  currency?: string | null
  notes?: string | null
}

/* -------------------------------------------------------------------------- */
/* Hostings                                                                   */
/* -------------------------------------------------------------------------- */

export interface Hosting {
  id: number
  name: string
  provider: RelationRef | null
  provider_id?: number | null
  client: RelationRef | null
  client_id?: number | null
  project: RelationRef | null
  project_id?: number | null
  status: EnumOption | null
  server_type: EnumOption | null
  ssl_certificate: EnumOption | null
  ip_address: string | null
  server_name: string | null
  control_panel_url: string | null
  username: string | null
  has_password: boolean
  ftp_host: string | null
  ftp_username: string | null
  has_ftp_password: boolean
  disk_space: string | null
  bandwidth: string | null
  start_date: string | null
  expiry_date: string | null
  days_until_expiry?: number | null
  auto_renewal: boolean
  expiry_notification: boolean
  price: number | null
  currency: string | null
  notes: string | null
  created_at: string | null
  updated_at?: string | null
  deleted_at?: string | null
}

export interface HostingPayload {
  name: string
  provider_id?: number | null
  client_id?: number | null
  project_id?: number | null
  status?: string | null
  server_type?: string | null
  ssl_certificate?: string | null
  ip_address?: string | null
  server_name?: string | null
  control_panel_url?: string | null
  username?: string | null
  password?: string | null
  ftp_host?: string | null
  ftp_username?: string | null
  ftp_password?: string | null
  disk_space?: string | null
  bandwidth?: string | null
  start_date?: string | null
  expiry_date?: string | null
  auto_renewal?: boolean
  expiry_notification?: boolean
  price?: number | null
  currency?: string | null
  notes?: string | null
}

/* -------------------------------------------------------------------------- */
/* Domains                                                                    */
/* -------------------------------------------------------------------------- */

export interface Domain {
  id: number
  name: string
  registrar: RelationRef | null
  registrar_id?: number | null
  provider: RelationRef | null
  provider_id?: number | null
  client: RelationRef | null
  client_id?: number | null
  project: RelationRef | null
  project_id?: number | null
  hosting: RelationRef | null
  hosting_id?: number | null
  status: EnumOption | null
  registrar_status: EnumOption | null
  type: EnumOption | null
  username: string | null
  has_password: boolean
  nameservers: string[] | null
  registration_date: string | null
  expiry_date: string | null
  days_until_expiry?: number | null
  auto_renewal: boolean
  whois_protection: boolean
  expiry_notification: boolean
  price: number | null
  currency: string | null
  notes: string | null
  created_at: string | null
  updated_at?: string | null
  deleted_at?: string | null
}

export interface DomainPayload {
  name: string
  registrar_id?: number | null
  provider_id?: number | null
  client_id?: number | null
  project_id?: number | null
  hosting_id?: number | null
  status?: string | null
  registrar_status?: string | null
  type?: string | null
  username?: string | null
  password?: string | null
  nameservers?: string[] | null
  registration_date?: string | null
  expiry_date?: string | null
  auto_renewal?: boolean
  whois_protection?: boolean
  expiry_notification?: boolean
  price?: number | null
  currency?: string | null
  notes?: string | null
}

/** Decrypted secrets returned by `/{resource}/{id}/credentials`. */
export interface Credentials {
  username?: string | null
  password?: string | null
  ftp_username?: string | null
  ftp_password?: string | null
  [key: string]: string | null | undefined
}

/* -------------------------------------------------------------------------- */
/* Servers & monitoring                                                       */
/* -------------------------------------------------------------------------- */

/** A monitored machine. Every field is server-owned; nothing is hardcoded. */
export interface Server {
  id: number
  name: string
  hostname: string | null
  ip_address: string | null
  os: EnumOption | null
  os_version: string | null
  monitoring_enabled: boolean
  health: EnumOption | null
  is_stale: boolean
  last_heartbeat_at: string | null
  seconds_since_heartbeat: number | null
  agent_version: string | null
  uptime_seconds: number | null
  open_alert_count: number
  heartbeat_interval_seconds: number | null
  heartbeat_grace_seconds: number | null
  cpu_warning_threshold: number | null
  cpu_critical_threshold: number | null
  memory_warning_threshold: number | null
  memory_critical_threshold: number | null
  disk_warning_threshold: number | null
  disk_critical_threshold: number | null
  hosting: RelationRef | null
  hosting_id?: number | null
  client: RelationRef | null
  client_id?: number | null
  project: RelationRef | null
  project_id?: number | null
  notes: string | null
  /**
   * Optional recent samples the list endpoint may embed so a table can draw a
   * sparkline without one request per row. Absent unless the API sends it.
   */
  recent_metrics?: Record<string, MetricPoint[]> | null
  latest_metrics?: LatestMetric[] | null
  created_at: string | null
  updated_at?: string | null
  deleted_at?: string | null
}

export interface ServerPayload {
  name: string
  hostname?: string | null
  ip_address?: string | null
  os?: string | null
  os_version?: string | null
  hosting_id?: number | null
  client_id?: number | null
  project_id?: number | null
  monitoring_enabled?: boolean
  heartbeat_interval_seconds?: number | null
  heartbeat_grace_seconds?: number | null
  cpu_warning_threshold?: number | null
  cpu_critical_threshold?: number | null
  memory_warning_threshold?: number | null
  memory_critical_threshold?: number | null
  disk_warning_threshold?: number | null
  disk_critical_threshold?: number | null
  notes?: string | null
}

/** Condensed server reference used by attention/alert payloads. */
export interface ServerSummary {
  id: number
  name: string
  hostname?: string | null
  health?: EnumOption | null
  monitoring_enabled?: boolean
  is_stale?: boolean
  last_heartbeat_at?: string | null
  seconds_since_heartbeat?: number | null
  open_alert_count?: number
  os?: EnumOption | null
}

export interface ServerAlert {
  id: number
  server_id?: number
  server: RelationRef | null
  severity: EnumOption | null
  type: string | null
  title: string | null
  message: string | null
  metric_type: EnumOption | null
  observed_value: number | null
  threshold_value: number | null
  started_at: string | null
  last_observed_at: string | null
  resolved_at: string | null
  occurrences: number | null
  is_open?: boolean
  is_acknowledged?: boolean
  acknowledged_at: string | null
  /** Scalar user id. The loaded relation arrives as `acknowledger`. */
  acknowledged_by: number | null
  acknowledger?: RelationRef | null
}

/**
 * A stored agent credential. There is deliberately no token field: the
 * plaintext exists only in the one-time `IssuedAgentToken` create response.
 */
export interface AgentCredential {
  id: number
  name: string | null
  token_id: string | null
  token_hint: string | null
  last_used_at: string | null
  expires_at: string | null
  revoked_at: string | null
  is_usable: boolean
  created_at: string | null
}

/** Response of issuing/rotating a credential — the only place plaintext lives. */
export interface IssuedAgentToken {
  token: string
  credential: AgentCredential
}

export interface AgentCredentialPayload {
  name?: string | null
  expires_at?: string | null
}

/**
 * OS-specific install steps for the collector agent.
 *
 * Every field is optional: the endpoint's payload keys were still settling while
 * this was built, so the component reads whichever of each pair is present.
 */
export interface AgentInstructions {
  os?: string | null
  flavour?: string | null
  label?: string | null
  shell?: string | null
  steps?: string[] | null
  /** The command to run. `install_command` is the shipped key. */
  install_command?: string | null
  script?: string | null
  command?: string | null
  interval_seconds?: number | null
  ingest_url?: string | null
  metrics_url?: string | null
  config_url?: string | null
  config_path?: string | null
  endpoint?: string | null
  notes?: string | null
}

/** One aggregated bucket of a metric series. */
export interface MetricPoint {
  t: string
  avg: number | null
  min: number | null
  max: number | null
}

/**
 * One metric series, normalised by the service layer.
 *
 * The API nests the descriptor under `metric` and carries the thresholds with
 * the series; both are flattened here so the chart has one shape to render.
 */
export interface MetricSeries {
  type: string
  label: string | null
  unit: string | null
  is_percentage: boolean
  source: string | null
  bucket: string | null
  truncated: boolean
  thresholds: { warning: number | null; critical: number | null }
  points: MetricPoint[]
}

export interface LatestMetric {
  type: { value: string; label: string }
  value: number | null
  unit: string | null
  recorded_at: string | null
}

/**
 * Fleet counters, normalised from the API's nested
 * `{servers, alerts, needs_attention}` payload into one flat set of tiles.
 */
export interface MonitoringSummary {
  total: number
  monitored: number
  healthy: number
  warning: number
  critical: number
  offline: number
  pending: number
  disabled: number
  /** Open alert totals by severity, straight from the API. */
  open_alerts: number
  needs_attention: number
}

/** Metric chart ranges offered by the API. */
export type MetricRange = '1h' | '6h' | '24h' | '7d' | '30d' | '90d'

/* -------------------------------------------------------------------------- */
/* Activity log                                                               */
/* -------------------------------------------------------------------------- */

export interface ActivityLog {
  id: number
  action: EnumOption | null
  description: string | null
  summary?: string | null
  subject_type: string | null
  subject_id: number | null
  user: RelationRef | null
  ip_address: string | null
  user_agent: string | null
  properties?: Record<string, unknown> | null
  created_at: string | null
}

/* -------------------------------------------------------------------------- */
/* Dashboard                                                                  */
/* -------------------------------------------------------------------------- */

export interface DashboardStats {
  total_hostings: number
  active_hostings: number
  total_domains: number
  active_domains: number
  expiring_hostings: number
  expiring_domains: number
}

export interface ExpiringItem {
  id: number
  name: string
  expiry_date: string | null
  days_until_expiry: number | null
  status: EnumOption | null
  client: RelationRef | null
  provider: RelationRef | null
}

/* -------------------------------------------------------------------------- */
/* Meta options                                                               */
/* -------------------------------------------------------------------------- */

export interface MetaOptions {
  hosting_statuses: EnumOption[]
  domain_statuses: EnumOption[]
  registrar_statuses: EnumOption[]
  domain_types: EnumOption[]
  server_types: EnumOption[]
  ssl_certificates: EnumOption[]
  provider_types: EnumOption[]
  provider_statuses: EnumOption[]
  project_statuses: EnumOption[]
  user_statuses: EnumOption[]
  roles: EnumOption[]
  permissions: EnumOption[]
  currencies: EnumOption[]
  expiry_window_days: EnumOption[]
  server_os: EnumOption[]
  server_health: EnumOption[]
  metric_types: EnumOption[]
  alert_severities: EnumOption[]
}
