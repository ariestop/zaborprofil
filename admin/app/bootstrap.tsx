import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { installGlobalClientErrorReporting } from '../shared/lib/client-error-reporter'
import { initializeAuthStore, parsePermissions, parseRoles } from '../stores/auth'
import { AdminApp } from './AdminApp'

export function bootstrapAdminApp(): void {
  const root = document.getElementById('admin-app')
  if (root === null) {
    return
  }

  initializeAuthStore({
    userEmail: root.dataset.userEmail ?? '',
    logoutUrl: root.dataset.logoutUrl ?? '/admin/logout',
    logoutToken: root.dataset.logoutToken ?? '',
    roles: parseRoles(root.dataset.roles),
    permissions: parsePermissions(root.dataset.permissions),
  })

  installGlobalClientErrorReporting()

  createRoot(root).render(
    <StrictMode>
      <AdminApp />
    </StrictMode>,
  )
}
