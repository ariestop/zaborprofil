import { lazy, Suspense, useEffect, useState } from 'react'
import { createBrowserRouter, Navigate, RouterProvider } from 'react-router-dom'
import { AdminShellLayout } from '../layouts/AdminShellLayout'
import { ErrorBoundary } from '../shared/ui/error-boundary'
import { PageLoadingState } from '../shared/ui/page-loading-state'
import {
  loadCrmPage,
  loadDashboardPage,
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
import { RouteGuard } from './RouteGuard'

const DashboardPage = lazy(loadDashboardPage)
const PagesPage = lazy(loadPagesPage)
const PageCreatePage = lazy(loadPageCreatePage)
const PageEditorPage = lazy(loadPageEditorPage)
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
        { path: '/admin/pages', element: <RouteGuard routeKey="pages"><PagesPage /></RouteGuard> },
        { path: '/admin/pages/new', element: <RouteGuard routeKey="pageNew"><PageCreatePage /></RouteGuard> },
        {
          // Один экран редактирования: вкладки переключаются без перемонтирования, чтобы не терять несохранённые правки.
          // Сегмент :tab разбирает PageEditorPage (builder — совместимый псевдоним «Контента»).
          path: '/admin/pages/:id',
          element: <RouteGuard routeKey="pageDetail"><PageEditorPage /></RouteGuard>,
          children: [
            { index: true, element: null },
            { path: ':tab', element: null },
          ],
        },
        { path: '/admin/media', element: <RouteGuard routeKey="media"><MediaPage /></RouteGuard> },
        { path: '/admin/seo', element: <RouteGuard routeKey="seo"><SeoPage /></RouteGuard> },
        // Список и карточка заявки — один экран без перемонтирования: карточка открывается справа, фильтры и поиск сохраняются.
        {
          path: '/admin/crm',
          element: <RouteGuard routeKey="crm"><CrmPage /></RouteGuard>,
          children: [
            { index: true, element: null },
            { path: ':leadId', element: null },
          ],
        },
        { path: '/admin/settings', element: <RouteGuard routeKey="settings"><SettingsPage /></RouteGuard> },
        { path: '/admin/settings/migrations', element: <RouteGuard routeKey="settingsMigrations"><SettingsMigrationsPage /></RouteGuard> },
        { path: '/admin/users', element: <RouteGuard routeKey="users"><UsersPage /></RouteGuard> },
        { path: '/admin/system', element: <RouteGuard routeKey="system"><SystemDashboardPage /></RouteGuard> },
        { path: '/admin/system/processes', element: <RouteGuard routeKey="systemProcesses"><SystemProcessesPage /></RouteGuard> },
        { path: '/admin/system/logs', element: <RouteGuard routeKey="systemLogs"><SystemLogsPage /></RouteGuard> },
        { path: '/admin/system/queues', element: <RouteGuard routeKey="systemQueues"><SystemQueuesPage /></RouteGuard> },
        { path: '/admin/system/cache', element: <RouteGuard routeKey="systemCache"><SystemCachePage /></RouteGuard> },
        { path: '/admin/system/database', element: <RouteGuard routeKey="systemDatabase"><SystemDatabasePage /></RouteGuard> },
        { path: '/admin/system/security', element: <RouteGuard routeKey="systemSecurity"><SystemSecurityPage /></RouteGuard> },
        { path: '/admin/system/backups', element: <RouteGuard routeKey="systemBackups"><SystemBackupsPage /></RouteGuard> },
        { path: '/admin/system/deploy', element: <RouteGuard routeKey="systemDeploy"><SystemDeployPage /></RouteGuard> },
        { path: '/admin/system/audit', element: <RouteGuard routeKey="systemAudit"><SystemAuditPage /></RouteGuard> },
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
