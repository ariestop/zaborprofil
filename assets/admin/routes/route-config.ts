export interface AdminRouteDefinition {
  key: string
  path: string
  title: string
  navTitle?: string
  section: 'content' | 'system'
}

export const adminRoutes: AdminRouteDefinition[] = [
  { key: 'dashboard', path: '/admin/dashboard', title: 'Панель управления', section: 'system' },
  { key: 'pages', path: '/admin/pages', title: 'Страницы', section: 'content' },
  { key: 'pageDetail', path: '/admin/pages/:id', title: 'Страница', section: 'content' },
  { key: 'pageBuilder', path: '/admin/pages/:id/builder', title: 'Page Builder', section: 'content' },
  { key: 'media', path: '/admin/media', title: 'Медиа', section: 'content' },
  { key: 'seo', path: '/admin/seo', title: 'SEO', section: 'content' },
  { key: 'crm', path: '/admin/crm', title: 'CRM', section: 'system' },
  { key: 'settings', path: '/admin/settings', title: 'Настройки', section: 'system' },
  { key: 'users', path: '/admin/users', title: 'Пользователи', section: 'system' },
]

export const sidebarRoutes = adminRoutes.filter((route) => !route.path.includes('/:'))
