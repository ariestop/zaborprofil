import { lazy, Suspense, useEffect, useState } from 'react'
import { createBrowserRouter, Navigate, RouterProvider } from 'react-router-dom'
import { AdminShellLayout } from '../layouts/AdminShellLayout'
import { ErrorBoundary } from '../shared/ui/error-boundary'
import { PageLoadingState } from '../shared/ui/page-loading-state'
import {
  loadCrmPage,
  loadDashboardPage,
  loadLeadDetailPage,
  loadMediaPage,
  loadPageCreatePage,
  loadPageEditorPage,
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
const PageCreatePage = lazy(loadPageCreatePage)
const PageEditorPage = lazy(loadPageEditorPage)
const MediaPage = lazy(loadMediaPage)
const SeoPage = lazy(loadSeoPage)
const CrmPage = lazy(loadCrmPage)
const LeadDetailPage = lazy(loadLeadDetailPage)
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

function RootLayout() {
  return (
    <ErrorBoundary>
      <Suspense fallback={<PageLoadingState />}>
        <AdminShellLayout />
      </Suspense>
    </ErrorBoundary>
  )
}

function createAdminRouter() {
  return createBrowserRouter([
    {
      element: <RootLayout />,
      children: [
        { path: '/admin', element: <Navigate to="/admin/dashboard" replace /> },
        { path: '/admin/dashboard', element: <DashboardPage /> },
        { path: '/admin/pages', element: <PagesPage /> },
        { path: '/admin/pages/new', element: <PageCreatePage /> },
        {
          // Один экран редактирования: вкладки переключаются без перемонтирования, чтобы не терять несохранённые правки.
          // Сегмент :tab разбирает PageEditorPage (builder — совместимый псевдоним «Контента»).
          path: '/admin/pages/:id',
          element: <PageEditorPage />,
          children: [
            { index: true, element: null },
            { path: ':tab', element: null },
          ],
        },
        { path: '/admin/media', element: <MediaPage /> },
        { path: '/admin/seo', element: <SeoPage /> },
        { path: '/admin/crm', element: <CrmPage /> },
        { path: '/admin/crm/:leadId', element: <LeadDetailPage /> },
        { path: '/admin/settings', element: <SettingsPage /> },
        { path: '/admin/settings/migrations', element: <SettingsMigrationsPage /> },
        { path: '/admin/users', element: <UsersPage /> },
        { path: '/admin/system', element: <SystemDashboardPage /> },
        { path: '/admin/system/processes', element: <SystemProcessesPage /> },
        { path: '/admin/system/logs', element: <SystemLogsPage /> },
        { path: '/admin/system/queues', element: <SystemQueuesPage /> },
        { path: '/admin/system/cache', element: <SystemCachePage /> },
        { path: '/admin/system/database', element: <SystemDatabasePage /> },
        { path: '/admin/system/security', element: <SystemSecurityPage /> },
        { path: '/admin/system/backups', element: <SystemBackupsPage /> },
        { path: '/admin/system/deploy', element: <SystemDeployPage /> },
        { path: '/admin/system/audit', element: <SystemAuditPage /> },
        { path: '*', element: <Navigate to="/admin/dashboard" replace /> },
      ],
    },
  ])
}

export function AdminRouter() {
  const [router] = useState(createAdminRouter)

  useEffect(() => {
    schedulePrefetchForCurrentRoute(window.location.pathname)
  }, [])

  return <RouterProvider router={router} />
}
