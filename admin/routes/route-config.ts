import type { AdminPermission } from '../entities/user/permissions'
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
  /** Раздел занимает всю область под шапкой без внешних отступов и карточки (рабочие места вроде «Заявок»). */
  fullBleed?: boolean
  /** Право, необходимое для раздела. Без него раздел доступен любой админской роли. */
  permission?: AdminPermission
}

export const adminRoutes: AdminRouteDefinition[] = [
  { key: 'dashboard', path: '/admin/dashboard', title: 'Сводка', section: 'system', navGroup: 'main', icon: 'dashboard', keywords: ['главная', 'панель управления', 'дашборд'], fullBleed: true },
  { key: 'crm', path: '/admin/crm', title: 'Заявки', section: 'system', navGroup: 'main', icon: 'leads', keywords: ['crm', 'лиды', 'клиенты'], fullBleed: true, permission: 'leads.view' },
  { key: 'leadDetail', path: '/admin/crm/:leadId', title: 'Заявка', parentKey: 'crm', section: 'system', fullBleed: true, permission: 'leads.view' },
  { key: 'pages', path: '/admin/pages', title: 'Страницы', section: 'content', navGroup: 'content', icon: 'pages', keywords: ['контент', 'конструктор', 'блоки'], permission: 'pages.view' },
  { key: 'pageNew', path: '/admin/pages/new', title: 'Новая страница', parentKey: 'pages', section: 'content', hideInNav: true, keywords: ['создать страницу'], permission: 'pages.create' },
  { key: 'pageDetail', path: '/admin/pages/:id', title: 'Редактор страницы', section: 'content', permission: 'pages.view' },
  { key: 'pageTab', path: '/admin/pages/:id/:tab', title: 'Редактор страницы', section: 'content', permission: 'pages.view' },
  { key: 'media', path: '/admin/media', title: 'Медиатека', section: 'content', navGroup: 'content', icon: 'media', keywords: ['медиа', 'фото', 'изображения', 'файлы', 'загрузить'], permission: 'media.upload' },
  { key: 'seo', path: '/admin/seo', title: 'SEO', section: 'content', navGroup: 'seo', icon: 'seo', keywords: ['редиректы', 'robots', '404', 'аудит'], permission: 'seo.edit' },
  { key: 'users', path: '/admin/users', title: 'Пользователи', navTitle: 'Пользователи и роли', section: 'system', navGroup: 'manage', icon: 'users', keywords: ['роли', 'пароль', 'доступ'], permission: 'users.manage' },
  { key: 'settings', path: '/admin/settings', title: 'Настройки', section: 'system', navGroup: 'manage', icon: 'settings', permission: 'settings.edit' },
  { key: 'systemAudit', path: '/admin/system/audit', title: 'Журнал действий', section: 'system', navGroup: 'manage', icon: 'audit', keywords: ['аудит', 'история изменений'], permission: 'system.view' },
  { key: 'system', path: '/admin/system', title: 'Обзор системы', section: 'system', navGroup: 'server', keywords: ['здоровье', 'health', 'статус'], permission: 'system.view' },
  { key: 'systemProcesses', path: '/admin/system/processes', title: 'Процессы и сервисы', parentKey: 'system', section: 'system', navGroup: 'server', keywords: ['php-fpm', 'nginx', 'перезапуск'], permission: 'system.view' },
  { key: 'systemLogs', path: '/admin/system/logs', title: 'Логи', parentKey: 'system', section: 'system', navGroup: 'server', permission: 'system.view' },
  { key: 'systemQueues', path: '/admin/system/queues', title: 'Очереди задач', parentKey: 'system', section: 'system', navGroup: 'server', keywords: ['messenger', 'очереди'], permission: 'system.view' },
  { key: 'systemCache', path: '/admin/system/cache', title: 'Кэш', parentKey: 'system', section: 'system', navGroup: 'server', permission: 'system.view' },
  { key: 'systemDatabase', path: '/admin/system/database', title: 'База данных', parentKey: 'system', section: 'system', navGroup: 'server', keywords: ['mysql'], permission: 'system.view' },
  { key: 'systemSecurity', path: '/admin/system/security', title: 'Безопасность', parentKey: 'system', section: 'system', navGroup: 'server', permission: 'system.view' },
  { key: 'systemBackups', path: '/admin/system/backups', title: 'Бэкапы', navTitle: 'Резервные копии', parentKey: 'system', section: 'system', navGroup: 'server', keywords: ['бэкап', 'backup'], permission: 'system.view' },
  { key: 'systemDeploy', path: '/admin/system/deploy', title: 'Деплой', parentKey: 'system', section: 'system', navGroup: 'server', keywords: ['релиз', 'release'], permission: 'system.view' },
  { key: 'settingsMigrations', path: '/admin/settings/migrations', title: 'Миграции', parentKey: 'settings', section: 'system', navGroup: 'server', keywords: ['doctrine', 'база данных'], permission: 'settings.edit' },
]

export const sidebarRoutes = adminRoutes.filter((route) => !route.path.includes('/:') && route.hideInNav !== true)

export function isRouteAllowed(route: AdminRouteDefinition, permissions: readonly AdminPermission[]): boolean {
  return route.permission === undefined || permissions.includes(route.permission)
}

export function findRoute(key: string): AdminRouteDefinition | undefined {
  return adminRoutes.find((route) => route.key === key)
}

/** Пункты группы меню; если переданы права пользователя, недоступные разделы отбрасываются. */
export function routesInNavGroup(group: AdminNavGroup, permissions?: readonly AdminPermission[]): AdminRouteDefinition[] {
  return sidebarRoutes.filter((route) => route.navGroup === group && (permissions === undefined || isRouteAllowed(route, permissions)))
}
