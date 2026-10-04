import type { NavIconName } from '../layouts/nav-icons'

/**
 * Группы бокового меню. Порядок в массиве `navGroups` задаёт порядок в меню.
 * Группа `server` по умолчанию свёрнута: технические разделы нужны редко и не должны теснить заявки и контент.
 */
export type AdminNavGroup = 'main' | 'content' | 'seo' | 'manage' | 'server'

export interface AdminNavGroupDefinition {
  key: AdminNavGroup
  /** Подпись группы; у основной группы подписи нет. */
  title: string | null
  collapsible?: boolean
  icon?: NavIconName
}

export const navGroups: AdminNavGroupDefinition[] = [
  { key: 'main', title: null },
  { key: 'content', title: 'Контент' },
  { key: 'seo', title: 'Продвижение' },
  { key: 'manage', title: 'Управление' },
  { key: 'server', title: 'Сервер', collapsible: true, icon: 'server' },
]

export interface AdminRouteDefinition {
  key: string
  path: string
  title: string
  navTitle?: string
  parentKey?: string
  section: 'content' | 'system'
  /** Группа бокового меню. Маршруты без группы в меню не выводятся. */
  navGroup?: AdminNavGroup
  icon?: NavIconName
  /** Дополнительные слова для поиска в палитре команд. */
  keywords?: string[]
  /** Маршрут доступен по прямой ссылке и в командной палитре, но не выводится в боковой навигации. */
  hideInNav?: boolean
}

export const adminRoutes: AdminRouteDefinition[] = [
  { key: 'dashboard', path: '/admin/dashboard', title: 'Сводка', section: 'system', navGroup: 'main', icon: 'dashboard', keywords: ['главная', 'панель управления', 'дашборд'] },
  { key: 'crm', path: '/admin/crm', title: 'Заявки', section: 'system', navGroup: 'main', icon: 'leads', keywords: ['crm', 'лиды', 'клиенты'] },
  { key: 'leadDetail', path: '/admin/crm/:leadId', title: 'Заявка', parentKey: 'crm', section: 'system' },
  { key: 'pages', path: '/admin/pages', title: 'Страницы', section: 'content', navGroup: 'content', icon: 'pages', keywords: ['контент', 'конструктор', 'блоки'] },
  { key: 'pageNew', path: '/admin/pages/new', title: 'Новая страница', parentKey: 'pages', section: 'content', hideInNav: true, keywords: ['создать страницу'] },
  { key: 'pageDetail', path: '/admin/pages/:id', title: 'Редактор страницы', section: 'content' },
  { key: 'pageTab', path: '/admin/pages/:id/:tab', title: 'Редактор страницы', section: 'content' },
  { key: 'media', path: '/admin/media', title: 'Медиатека', section: 'content', navGroup: 'content', icon: 'media', keywords: ['медиа', 'фото', 'изображения', 'файлы', 'загрузить'] },
  { key: 'seo', path: '/admin/seo', title: 'SEO', section: 'content', navGroup: 'seo', icon: 'seo', keywords: ['редиректы', 'robots', '404', 'аудит'] },
  { key: 'users', path: '/admin/users', title: 'Пользователи', navTitle: 'Пользователи и роли', section: 'system', navGroup: 'manage', icon: 'users', keywords: ['роли', 'пароль', 'доступ'] },
  { key: 'settings', path: '/admin/settings', title: 'Настройки', section: 'system', navGroup: 'manage', icon: 'settings' },
  { key: 'systemAudit', path: '/admin/system/audit', title: 'Журнал действий', section: 'system', navGroup: 'manage', icon: 'audit', keywords: ['аудит', 'история изменений'] },
  { key: 'system', path: '/admin/system', title: 'Обзор системы', section: 'system', navGroup: 'server', keywords: ['здоровье', 'health', 'статус'] },
  { key: 'systemProcesses', path: '/admin/system/processes', title: 'Процессы и сервисы', parentKey: 'system', section: 'system', navGroup: 'server', keywords: ['php-fpm', 'nginx', 'перезапуск'] },
  { key: 'systemLogs', path: '/admin/system/logs', title: 'Логи', parentKey: 'system', section: 'system', navGroup: 'server' },
  { key: 'systemQueues', path: '/admin/system/queues', title: 'Очереди задач', parentKey: 'system', section: 'system', navGroup: 'server', keywords: ['messenger', 'очереди'] },
  { key: 'systemCache', path: '/admin/system/cache', title: 'Кэш', parentKey: 'system', section: 'system', navGroup: 'server' },
  { key: 'systemDatabase', path: '/admin/system/database', title: 'База данных', parentKey: 'system', section: 'system', navGroup: 'server', keywords: ['mysql'] },
  { key: 'systemSecurity', path: '/admin/system/security', title: 'Безопасность', parentKey: 'system', section: 'system', navGroup: 'server' },
  { key: 'systemBackups', path: '/admin/system/backups', title: 'Бэкапы', navTitle: 'Резервные копии', parentKey: 'system', section: 'system', navGroup: 'server', keywords: ['бэкап', 'backup'] },
  { key: 'systemDeploy', path: '/admin/system/deploy', title: 'Деплой', parentKey: 'system', section: 'system', navGroup: 'server', keywords: ['релиз', 'release'] },
  { key: 'settingsMigrations', path: '/admin/settings/migrations', title: 'Миграции', parentKey: 'settings', section: 'system', navGroup: 'server', keywords: ['doctrine', 'база данных'] },
]

export const sidebarRoutes = adminRoutes.filter((route) => !route.path.includes('/:') && route.hideInNav !== true)

export function routesInNavGroup(group: AdminNavGroup): AdminRouteDefinition[] {
  return sidebarRoutes.filter((route) => route.navGroup === group)
}
