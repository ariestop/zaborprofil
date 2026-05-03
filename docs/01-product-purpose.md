# 01. Продуктовые цели и user journeys

## Бизнес-цель

Дать `zaborprofil.ru` управляемую CMS-платформу с предсказуемой эксплуатацией, контролируемым SEO и пространством для роста до e-commerce и B2B-кабинетов.

## Что закрывают сегодня

| Сценарий | Кто | Что | Как |
|---|---|---|---|
| Просмотр статической страницы / лендинга | Посетитель | Получает SEO-friendly страницу | SSR через `PublicPageController` + Twig partial’ы блоков |
| Управление страницами | Редактор | Создаёт/редактирует/публикует страницы | Admin API `/admin/api/content/pages` + Vue admin SPA |
| Управление SEO redirects | SEO | Заводит 301 при смене URL | Admin API + `RedirectKernelSubscriber` |
| Доступ в админку | Администратор | Логинится, получает RBAC | `AdminUser` + Symfony Security + `AdminPermissionVoter` |
| Sitemap / robots | Поисковик | Получает индексируемые URL | `SitemapController`, `RobotsController` |

## Что закрывают завтра (целевое состояние)

- Media Library с безопасной загрузкой и optimisation.
- Menu-модуль с управляемыми меню разных позиций.
- Lead-модуль с антиспамом, honeypot, rate limiting, нотификациями.
- Catalog/Product/Cart/Order для интернет-магазина.
- Customer/Partner-кабинеты.
- AuditLog для критичных действий.
- Полная SEO-валидация перед публикацией.

## User journeys

### Посетитель публичного сайта

1. Открывает URL → Nginx → PHP-FPM → Symfony Kernel.
2. Router находит `PublicPageController` (catch-all с приоритетом `-100`).
3. `PublicPageResolver` ищет `Page` по нормализованному `path` со статусом `published`.
4. Возвращает `404` для черновиков/архивных, либо рендерит `public/page/show.html.twig` с включёнными `PageBlock`.
5. Subscribers добавляют security headers и `request_id`.

### Редактор контента

1. Открывает `/admin/login` → Symfony form login → admin firewall.
2. Получает Vue admin shell с CSRF token в `<meta>`.
3. Vue SPA шлёт запросы на `/admin/api/content/...`, передавая `X-CSRF-Token` и same-origin `Origin`.
4. Запрос проходит `AdminApiCsrfSubscriber`, `AdminApiOriginSubscriber`, `AdminPermissionVoter`.
5. Контроллер вызывает application handler (`CreatePageHandler`, `PublishPageHandler` и т.д.).
6. Handler работает только с domain entity и repository interface; Doctrine реализация подхватывается DI.
7. Response — JSON через `ContentApiResponder`.

### Администратор системы (целевой)

1. Управляет пользователями (`User` модуль), ролями.
2. Конфигурирует `Settings`.
3. Видит AuditLog критичных действий.
4. Запускает целевые console commands (`app:seo:audit`, `app:cache:warmup`, `app:sitemap:generate`).

### SEO-специалист

1. Меняет `title`, `description`, `h1`, `canonical`, `robots` на `Page`.
2. Заводит `Redirect` при изменении URL.
3. Проверяет sitemap и robots.txt.
4. Использует целевой `app:seo:audit` перед релизом.

### Будущий B2B/B2C-пользователь (целевой)

1. Регистрируется как `Customer` или `Partner`.
2. Входит в личный кабинет.
3. Просматривает каталог и оформляет заказ или заявку.
4. Получает уведомления через Symfony Mailer + Messenger.

## Ограничения системы

- Один `AdminUser`-провайдер; публичной регистрации нет (целевая фича — `Customer`).
- Web root жёстко прибит к `public_html/`.
- Production деплой только через release-структуру, ручные SSH-изменения файлов внутри `releases/<...>` запрещены.
- Контент пишется на русском; английский допустим только в технических полях.

## Метрики продуктового успеха (целевые)

- Time to first byte для публичных страниц `<= 200 мс` после warm cache.
- Покрытие тестами критичных путей (Content engine, Auth, Redirects) `>= 80%` строк.
- Все опубликованные страницы — в sitemap, без 5xx и без дублирующихся URL.
- 100% изменений `Page.path` сопровождаются 301 Redirect.

См. [00-overview](00-overview.md) и [45-roadmap-and-extension-points](45-roadmap-and-extension-points.md).
