import { lazy, Suspense, useEffect } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AdminShellLayout } from '../layouts/AdminShellLayout'
import { ErrorBoundary } from '../shared/ui/error-boundary'
import { PageLoadingState } from '../shared/ui/page-loading-state'
import {
  loadCrmPage,
  loadDashboardPage,
  loadMediaPage,
  loadPageBuilderPage,
  loadPageDetailPage,
  loadPagesPage,
  loadSeoPage,
  loadSettingsPage,
  loadSettingsMigrationsPage,
  loadSystemAuditPage,
  loadSystemBackupsPage,
  loadSystemCachePage,
  loadSystemDashboardPage,
  loadSystemDatabasePage,
  loadSystemDeployPage,
  loadSystemLogsPage,
  loadSystemProcessesPage,
  loadSystemQueuesPage,
  loadSystemSecurityPage,
  loadUsersPage,
} from './loaders'
import { schedulePrefetchForCurrentRoute } from './prefetch'

const DashboardPage = lazy(loadDashboardPage)
const PagesPage = lazy(loadPagesPage)
const PageDetailPage = lazy(loadPageDetailPage)
const PageBuilderPage = lazy(loadPageBuilderPage)
const MediaPage = lazy(loadMediaPage)
const SeoPage = lazy(loadSeoPage)
const CrmPage = lazy(loadCrmPage)
const SettingsPage = lazy(loadSettingsPage)
const SettingsMigrationsPage = lazy(loadSettingsMigrationsPage)
const UsersPage = lazy(loadUsersPage)
const SystemDashboardPage = lazy(loadSystemDashboardPage)
const SystemProcessesPage = lazy(loadSystemProcessesPage)
const SystemLogsPage = lazy(loadSystemLogsPage)
const SystemQueuesPage = lazy(loadSystemQueuesPage)
const SystemCachePage = lazy(loadSystemCachePage)
const SystemDatabasePage = lazy(loadSystemDatabasePage)
const SystemSecurityPage = lazy(loadSystemSecurityPage)
const SystemBackupsPage = lazy(loadSystemBackupsPage)
const SystemDeployPage = lazy(loadSystemDeployPage)
const SystemAuditPage = lazy(loadSystemAuditPage)

export function AdminRouter() {
  useEffect(() => {
    schedulePrefetchForCurrentRoute(window.location.pathname)
  }, [])

  return (
    <BrowserRouter>
      <ErrorBoundary>
        <Suspense fallback={<PageLoadingState />}>
          <Routes>
            <Route element={<AdminShellLayout />}>
              <Route path="/admin" element={<Navigate to="/admin/dashboard" replace />} />
              <Route path="/admin/dashboard" element={<DashboardPage />} />
              <Route path="/admin/pages" element={<PagesPage />} />
              <Route path="/admin/pages/:id" element={<PageDetailPage />} />
              <Route path="/admin/pages/:id/builder" element={<PageBuilderPage />} />
              <Route path="/admin/media" element={<MediaPage />} />
              <Route path="/admin/seo" element={<SeoPage />} />
              <Route path="/admin/crm" element={<CrmPage />} />
              <Route path="/admin/settings" element={<SettingsPage />} />
              <Route path="/admin/settings/migrations" element={<SettingsMigrationsPage />} />
              <Route path="/admin/users" element={<UsersPage />} />
              <Route path="/admin/system" element={<SystemDashboardPage />} />
              <Route path="/admin/system/processes" element={<SystemProcessesPage />} />
              <Route path="/admin/system/logs" element={<SystemLogsPage />} />
              <Route path="/admin/system/queues" element={<SystemQueuesPage />} />
              <Route path="/admin/system/cache" element={<SystemCachePage />} />
              <Route path="/admin/system/database" element={<SystemDatabasePage />} />
              <Route path="/admin/system/security" element={<SystemSecurityPage />} />
              <Route path="/admin/system/backups" element={<SystemBackupsPage />} />
              <Route path="/admin/system/deploy" element={<SystemDeployPage />} />
              <Route path="/admin/system/audit" element={<SystemAuditPage />} />
              <Route path="*" element={<Navigate to="/admin/dashboard" replace />} />
            </Route>
          </Routes>
        </Suspense>
      </ErrorBoundary>
    </BrowserRouter>
  )
}
