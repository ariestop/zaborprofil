import { lazy, Suspense } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AdminShellLayout } from '../layouts/AdminShellLayout'
import { ErrorBoundary } from '../shared/ui/error-boundary'
import { PageLoadingState } from '../shared/ui/page-loading-state'

const DashboardPage = lazy(() => import('../pages/DashboardPage'))
const PagesPage = lazy(() => import('../pages/PagesPage'))
const PageDetailPage = lazy(() => import('../pages/PageDetailPage'))
const PageBuilderPage = lazy(() => import('../pages/PageBuilderPage'))
const MediaPage = lazy(() => import('../pages/MediaPage'))
const SeoPage = lazy(() => import('../pages/SeoPage'))
const CrmPage = lazy(() => import('../pages/CrmPage'))
const SettingsPage = lazy(() => import('../pages/SettingsPage'))
const UsersPage = lazy(() => import('../pages/UsersPage'))

export function AdminRouter() {
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
              <Route path="/admin/users" element={<UsersPage />} />
              <Route path="*" element={<Navigate to="/admin/dashboard" replace />} />
            </Route>
          </Routes>
        </Suspense>
      </ErrorBoundary>
    </BrowserRouter>
  )
}
