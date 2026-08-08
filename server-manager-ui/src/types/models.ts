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
}
