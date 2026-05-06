export type AdminRouteName = 'dashboard' | 'content' | 'media' | 'menu' | 'leads' | 'settings' | 'settingsMigrations' | 'redirects' | 'systemHealth' | 'maintenance' | 'auditLog'

export interface AdminRoute {
  name: AdminRouteName
  path: string
  title: string
  navTitle?: string
  parentName?: AdminRouteName
}

export const routes: AdminRoute[] = [
  { name: 'dashboard', path: '/admin/dashboard', title: 'Панель управления' },
  { name: 'content', path: '/admin/content/pages', title: 'Страницы' },
  { name: 'media', path: '/admin/media', title: 'Медиа' },
  { name: 'menu', path: '/admin/menu', title: 'Меню' },
  { name: 'leads', path: '/admin/leads', title: 'Заявки' },
  { name: 'settings', path: '/admin/settings', title: 'Настройки' },
  { name: 'settingsMigrations', path: '/admin/settings/migrations', title: 'Настройки → Миграции', navTitle: 'Миграции', parentName: 'settings' },
  { name: 'redirects', path: '/admin/seo/redirects', title: 'Редиректы' },
  { name: 'systemHealth', path: '/admin/system/health', title: 'Health Center' },
  { name: 'maintenance', path: '/admin/system/maintenance', title: 'Maintenance' },
  { name: 'auditLog', path: '/admin/system/audit', title: 'Audit Log' },
]

export function routeNameByPath(pathname: string): AdminRouteName {
  return routes.find((route) => route.path === pathname)?.name ?? 'dashboard'
}

export function currentRouteName(pathname = window.location.pathname): AdminRouteName {
  return routeNameByPath(pathname)
}
