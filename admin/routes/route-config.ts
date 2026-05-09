export interface AdminRouteDefinition {
  key: string
  path: string
  title: string
  navTitle?: string
  parentKey?: string
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
  { key: 'settingsMigrations', path: '/admin/settings/migrations', title: 'Миграции', parentKey: 'settings', section: 'system' },
  { key: 'users', path: '/admin/users', title: 'Пользователи', section: 'system' },
  { key: 'system', path: '/admin/system', title: 'Обзор системы', navTitle: 'System Center', section: 'system' },
  { key: 'systemProcesses', path: '/admin/system/processes', title: 'Процессы и сервисы', parentKey: 'system', section: 'system' },
  { key: 'systemLogs', path: '/admin/system/logs', title: 'Логи', parentKey: 'system', section: 'system' },
  { key: 'systemQueues', path: '/admin/system/queues', title: 'Очереди Symfony Messenger', parentKey: 'system', section: 'system' },
  { key: 'systemCache', path: '/admin/system/cache', title: 'Кэш', parentKey: 'system', section: 'system' },
  { key: 'systemDatabase', path: '/admin/system/database', title: 'База данных', parentKey: 'system', section: 'system' },
  { key: 'systemSecurity', path: '/admin/system/security', title: 'Безопасность', parentKey: 'system', section: 'system' },
  { key: 'systemBackups', path: '/admin/system/backups', title: 'Бэкапы', parentKey: 'system', section: 'system' },
  { key: 'systemDeploy', path: '/admin/system/deploy', title: 'Деплой', parentKey: 'system', section: 'system' },
  { key: 'systemAudit', path: '/admin/system/audit', title: 'Аудит действий админов', parentKey: 'system', section: 'system' },
]

export const sidebarRoutes = adminRoutes.filter((route) => !route.path.includes('/:'))
