# Menu

Модуль управляет публичной навигацией сайта.

- Поддерживаемые позиции: `header`, `footer`, `service`.
- Admin API: `/admin/api/menu/items` для list/create/update/delete.
- Twig helper: `menu_items(position)` возвращает активные пункты из кэша `cache.menu`.
- При create/update/delete `MenuProvider::invalidate()` сбрасывает затронутые позиции.
- `BreadcrumbBuilder` строит HTML breadcrumbs для публичных страниц; `SchemaOrgBuilder` добавляет JSON-LD `BreadcrumbList`.
