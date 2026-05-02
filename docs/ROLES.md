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
- `pages.delete`
- `seo.edit`
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
