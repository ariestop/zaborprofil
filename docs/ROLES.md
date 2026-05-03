# Роли и права админки

Админка использует Symfony Security roles и permission voter.

## Роли

- `ROLE_SUPER_ADMIN` — полный доступ, включая управление пользователями.
- `ROLE_ADMIN` — управление контентом, SEO, заявками, настройками и системными разделами.
- `ROLE_EDITOR` — создание и редактирование страниц и медиа-загрузка.
- `ROLE_SEO` — SEO-настройки, редиректы, sitemap/robots на будущих этапах.
- `ROLE_MANAGER` — заявки.

## Права

Права описаны в `App\Module\Auth\Domain\Security\AdminPermission`:

- `pages.view`
- `pages.create`
- `pages.edit`
- `pages.publish`
- `pages.submit_review`
- `pages.approve`
- `pages.unpublish`
- `pages.schedule`
- `pages.archive`
- `pages.view_revisions`
- `pages.rollback_revision`
- `pages.manage_templates`
- `pages.delete`
- `blocks.create`
- `blocks.edit`
- `blocks.delete`
- `blocks.reorder`
- `blocks.clone`
- `seo.edit`
- `seo.approve`
- `media.upload`
- `media.delete`
- `leads.view`
- `leads.manage`
- `settings.edit`
- `users.manage`
- `system.view`
- `system.manage`

На этапе W1 права проверяются централизованным `AdminPermissionVoter`. Когда появятся
сложные правила на уровне конкретных сущностей, их можно выделить в отдельные
resource voters.
