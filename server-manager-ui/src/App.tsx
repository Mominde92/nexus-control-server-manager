import { Navigate, Outlet, Route, Routes } from 'react-router-dom'
import { useAuth } from '@/hooks/use-auth'
import { AppLayout } from '@/components/layout/app-layout'
import { LoginPage } from '@/pages/login-page'
import { DashboardPage } from '@/pages/dashboard-page'
import { ResourcePage } from '@/pages/resource-page'
import { ForgotPasswordPage, ResetPasswordPage } from '@/pages/password-recovery-pages'
import { SettingsPage } from '@/pages/settings-page'
import { ResourceRecordPage } from '@/pages/resource-record-page'
import { ResourceAccessPage } from '@/pages/resource-access-page'
import { RolesPage } from '@/pages/roles-page'
import { UserFormPage } from '@/pages/user-form-page'
import { RoleRecordPage } from '@/pages/role-record-page'
import { MonitoringDashboardPage } from '@/pages/monitoring/monitoring-dashboard-page'
import { ServerListPage } from '@/pages/monitoring/server-list-page'
import { ServerDetailPage } from '@/pages/monitoring/server-detail-page'
import { ServerFormPage } from '@/pages/monitoring/server-form-page'
import { AlertListPage } from '@/pages/monitoring/alert-list-page'

function Protected() {
  const { isLoading, isAuthenticated } = useAuth()
  if (isLoading) return <div className="grid min-h-screen place-items-center text-muted-foreground">Loading Server Manager…</div>
  return isAuthenticated ? <Outlet /> : <Navigate to="/login" replace />
}

export default function App() {
  return <Routes>
    <Route path="/login" element={<LoginPage />} />
    <Route path="/forgot-password" element={<ForgotPasswordPage />} />
    <Route path="/reset-password" element={<ResetPasswordPage />} />
    <Route element={<Protected />}>
      <Route element={<AppLayout />}>
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="dashboard" element={<DashboardPage />} />
        {['hostings','domains','providers','clients','projects'].map((resource) => <Route key={resource}>
          <Route path={resource} element={<ResourcePage resource={resource} />} />
          <Route path={`${resource}/new`} element={<ResourceRecordPage resource={resource} mode="create" />} />
          <Route path={`${resource}/:id`} element={<ResourceRecordPage resource={resource} mode="view" />} />
          <Route path={`${resource}/:id/edit`} element={<ResourceRecordPage resource={resource} mode="edit" />} />
          <Route path={`${resource}/:id/delete`} element={<ResourceRecordPage resource={resource} mode="delete" />} />
          {(['hostings','domains'].includes(resource))&&<Route path={`${resource}/:id/access`} element={<ResourceAccessPage resource={resource as 'hostings'|'domains'} />} />}
        </Route>)}
        <Route path="monitoring" element={<MonitoringDashboardPage />} />
        <Route path="servers" element={<ServerListPage />} />
        <Route path="servers/new" element={<ServerFormPage mode="create" />} />
        <Route path="servers/:id" element={<ServerDetailPage />} />
        <Route path="servers/:id/edit" element={<ServerFormPage mode="edit" />} />
        <Route path="server-alerts" element={<AlertListPage />} />
        <Route path="users" element={<ResourcePage resource="users" />} />
        <Route path="users/new" element={<UserFormPage mode="create" />} />
        <Route path="users/:id/edit" element={<UserFormPage mode="edit" />} />
        <Route path="users/:id" element={<ResourceRecordPage resource="users" mode="view" />} />
        <Route path="users/:id/delete" element={<ResourceRecordPage resource="users" mode="delete" />} />
        <Route path="activity-logs" element={<ResourcePage resource="activity-logs" />} />
        <Route path="roles" element={<RolesPage />} />
        <Route path="roles/new" element={<RoleRecordPage mode="create" />} />
        <Route path="roles/:id" element={<RoleRecordPage mode="view" />} />
        <Route path="roles/:id/edit" element={<RoleRecordPage mode="edit" />} />
        <Route path="roles/:id/delete" element={<RoleRecordPage mode="delete" />} />
        <Route path="settings" element={<SettingsPage />} />
      </Route>
    </Route>
    <Route path="*" element={<Navigate to="/dashboard" replace />} />
  </Routes>
}
