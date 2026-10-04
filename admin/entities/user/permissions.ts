/**
 * Права админки. Список зеркалит `App\Module\Auth\Domain\Security\AdminPermission`;
 * соответствие проверяет `permissions.spec.ts`. Сервер проверяет право на каждый запрос,
 * на клиенте права нужны только чтобы не показывать недоступное.
 */
export const ADMIN_PERMISSIONS = [
  'pages.view',
  'pages.create',
  'pages.edit',
  'pages.publish',
  'pages.submit_review',
  'pages.approve',
  'pages.unpublish',
  'pages.schedule',
  'pages.archive',
  'pages.view_revisions',
  'pages.rollback_revision',
  'pages.manage_templates',
  'pages.delete',
  'blocks.create',
  'blocks.edit',
  'blocks.delete',
  'blocks.reorder',
  'blocks.clone',
  'seo.edit',
  'seo.approve',
  'media.upload',
  'media.delete',
  'leads.view',
  'leads.manage',
  'leads.export',
  'catalog.view',
  'catalog.manage',
  'settings.edit',
  'users.manage',
  'system.view',
  'system.manage',
  'system.dangerous',
] as const

export type AdminPermission = (typeof ADMIN_PERMISSIONS)[number]

export function isAdminPermission(value: string): value is AdminPermission {
  return (ADMIN_PERMISSIONS as readonly string[]).includes(value)
}
