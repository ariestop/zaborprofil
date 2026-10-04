import { ADMIN_PERMISSIONS, type AdminPermission } from '../../entities/user/permissions'
import { initializeAuthStore } from '../../stores/auth'

/** Права ролей без иерархии; повторяют `AdminPermissionVoter` и проверяются backend-тестом `AdminRoleAccessTest`. */
export const ROLE_PERMISSIONS: Record<'ROLE_EDITOR' | 'ROLE_SEO' | 'ROLE_MANAGER', AdminPermission[]> = {
  ROLE_EDITOR: [
    'pages.view', 'pages.create', 'pages.edit', 'pages.submit_review', 'pages.view_revisions',
    'blocks.create', 'blocks.edit', 'blocks.reorder', 'blocks.clone', 'media.upload', 'catalog.view',
  ],
  ROLE_SEO: ['pages.view', 'pages.submit_review', 'pages.approve', 'pages.view_revisions', 'seo.edit', 'seo.approve'],
  ROLE_MANAGER: ['leads.view', 'leads.manage', 'catalog.view'],
}

export function loginAs(role: keyof typeof ROLE_PERMISSIONS | 'ROLE_ADMIN'): void {
  initializeAuthStore({
    userEmail: `${role.toLowerCase()}@example.test`,
    logoutUrl: '/admin/logout',
    logoutToken: 'token',
    roles: [role],
    permissions: role === 'ROLE_ADMIN'
      ? ADMIN_PERMISSIONS.filter((permission) => permission !== 'system.dangerous')
      : ROLE_PERMISSIONS[role],
  })
}
