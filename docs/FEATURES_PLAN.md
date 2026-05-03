# План расширения CMS

Этот документ фиксирует утвержденный порядок внедрения профессиональных
возможностей CMS.

## Реализовано

### W0 Foundations

Состав:

- Vue admin shell;
- модуль `Settings`;
- модуль `Seo\Redirect`;
- базовые helper-ы для timestamps, soft delete и ULID;
- миграции и тесты для W0.

### W1 Security baseline

Состав:

- RBAC role hierarchy;
- `AdminPermission` и централизованный voter;
- Origin/Referer защита `/admin/api/*`;
- security headers;
- базовая upload security policy;
- миграция для выдачи `ROLE_SUPER_ADMIN` существующим админам.

### W2 Logging

Состав:

- `X-Request-Id` для каждого HTTP-запроса;
- Monolog processors для request/user/release;
- PII masking processor;
- отдельные log channels и файлы;
- Telegram error sink через Messenger;
- документация `docs/LOGGING.md`.

### W3 Health and diagnostics

Состав:

- `/health/live` и `/health/ready`;
- расширяемый `HealthCheckInterface`;
- System Health Center в админке;
- `app:system:diagnostics`;
- Admin System Warnings;
- документация `docs/HEALTH_CHECKS.md`.

### W4 Maintenance, audit log, business events

Состав:

- Maintenance Mode через CLI и админку;
- whitelist IP;
- страница техобслуживания;
- Admin Audit Log с diff old/new values;
- Business Events Log;
- документация `docs/MAINTENANCE.md`, `docs/AUDIT_LOG.md`, `docs/BUSINESS_EVENTS.md`.

### W5 DevOps safety

Состав:

- Backup Manager на уровне deploy-скриптов: PostgreSQL dump + uploads archive перед production миграциями;
- проверка backup-файлов (`pg_restore -l`, `tar -tzf`) и retention через `BACKUP_RETENTION_DAYS`;
- Deployment Log в `shared/deployments/deployments.jsonl`;
- расширенный Rollback Manager: `rollback.sh --list`, rollback по имени/пути релиза, health-check после rollback;
- Deploy Safety Checklist gate для production через `CONFIRM_DEPLOY_SAFETY_CHECKLIST=yes`;
- защита от параллельных deploy/rollback через lock directory;
- Staging Protection: production job зависит от staging job, production script требует `CONFIRM_STAGING_DEPLOYED=yes`, опционально поддерживается marker `REQUIRE_STAGING_MARKER=yes`.

### W6 SEO core

Состав:

- расширенные SEO-поля страниц: description, canonical, OpenGraph, JSON-LD;
- Advanced Sitemap Manager: sitemap index + чанки `/sitemap-pages-N.xml`;
- Robots Manager: production robots.txt через `Settings`, non-prod `Disallow: /`;
- Schema.org Builder: базовый `WebPage` JSON-LD для публичных страниц;
- Canonical URL Guard с проверкой host из `SITE_URL`.

### W7 SEO audit and pre-publish checklist

Состав:

- SEO Audit Engine с severity P0/P1/P2;
- bulk audit через `app:seo:audit`;
- page audit через `GET /admin/api/seo/audit/pages/{id}`;
- Pre-Publish Checklist с блокировкой P0/P1 ошибок;
- рекомендации P2 без блокировки публикации.

### W8 Cache invalidation and preview links

Состав:

- tag-aware cache для публичных страниц через `cache.public_page`;
- правила инвалидации для страниц, блоков, SEO metadata, settings, robots и redirects;
- preview links для черновиков через HMAC token;
- `X-Robots-Tag: noindex,nofollow` и `<meta name="robots" content="noindex, nofollow">` для preview.

### W9 Media

Состав:

- Media Library: upload/list/delete в админке;
- Media Optimizer на GD: re-encode оригинала для strip metadata и генерация responsive variants;
- WebP/AVIF thumbnails в `public_html/uploads/media/variants/` при поддержке PHP runtime;
- EXIF/metadata strip через re-encode загруженных raster images;
- Safe SVG policy: SVG запрещён по умолчанию, sanitization оставлена отдельным future extension point.

### W9.1 Menu

Состав:

- управляемые позиции `header`, `footer`, `service` в admin API/UI;
- публичный layout рендерит header/footer/service navigation через `menu_items(position)`;
- breadcrumbs для публичных страниц + JSON-LD `BreadcrumbList`;
- sitemap page-source и chunking остаются в `SitemapBuilder` (`/sitemap.xml`, `/sitemap-pages-N.xml`);
- кэш меню `cache.menu` инвалидируется при create/update/delete пунктов.

### W10 Leads

Состав:

- Lead Pipeline: статусы `new`, `in_progress`, `done`, `spam` и admin status update;
- публичная SSR lead-форма на страницах с отправкой в `/api/leads`;
- Anti-Spam Layer: honeypot, минимальное время заполнения, IP rate limit, link scoring;
- 152-ФЗ consent snapshot: текст согласия, policy URL, page URL, IP, User-Agent, timestamp;
- email notifications и optional Telegram notifications для заявок.

## Оставшиеся задачи на будущее

### W11 Dev/QA and documentation

- `make init`;
- расширенный `make quality`;
- `app:smoke:test`;
- финальное обновление документации администратора и редактора.

## Отложено до публичного запуска

- Notification Center;
- Draft Autosave;
- Revision History;
- Page Templates Library;
- Blocks Library;
- Cache Dashboard;
- Public Cache Warmup;
- Performance Profiling;
- E2E Playwright;
- 2FA TOTP;
- full-page Redis cache + ETag;
- AI alt/SEO automation;
- подготовка интернет-магазина и B2B/B2C кабинетов.
