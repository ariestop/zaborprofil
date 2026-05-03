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

## Оставшиеся задачи на будущее

### W5 DevOps safety

- Backup Manager;
- Deployment Log;
- расширенный Rollback Manager;
- Deploy Safety Checklist;
- Staging Protection.

### W6 SEO core

- расширенные SEO-поля страниц;
- Advanced Sitemap Manager;
- Robots Manager;
- Schema.org Builder;
- Canonical URL Guard.

### W7 SEO audit and pre-publish checklist

- SEO Audit Engine;
- bulk audit;
- сохранение результатов аудита;
- Pre-Publish Checklist с блокировкой P0/P1 ошибок.

### W8 Cache invalidation and preview links

- tag-aware cache;
- правила инвалидации для страниц, блоков, настроек, SEO и redirects;
- preview links для черновиков;
- `X-Robots-Tag: noindex,nofollow` для preview.

### W9 Media

- Media Library;
- Media Optimizer на Imagick;
- WebP/AVIF/thumbnails;
- EXIF strip;
- Safe SVG policy.

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
