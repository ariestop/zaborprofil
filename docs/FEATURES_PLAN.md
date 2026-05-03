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

## Оставшиеся задачи на будущее

### W8 Cache invalidation and preview links

- tag-aware cache для публичных страниц;
- правила инвалидации для страниц, блоков, настроек, SEO и redirects;
- preview links для черновиков;
- `X-Robots-Tag: noindex,nofollow` для preview.

### W9 Media

- Media Library;
- Media Optimizer на Imagick;
- WebP/AVIF/thumbnails;
- EXIF strip;
- Safe SVG policy.

### W9.1 Menu

- управляемые меню для header/footer/service navigation;
- breadcrumbs для публичных страниц;
- sitemap sources и chunking;
- кэш меню с инвалидацией.

### W10 Leads

- Lead Pipeline;
- публичные lead-формы;
- Anti-Spam Layer;
- 152-ФЗ consent snapshot;
- Telegram notifications для заявок.

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
