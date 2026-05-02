# План расширения CMS

Этот документ фиксирует утвержденный порядок внедрения профессиональных
возможностей CMS.

## Текущая волна: W0 Foundations

Состав:

- Vue admin shell;
- модуль `Settings`;
- модуль `Seo\Redirect`;
- базовые helper-ы для timestamps, soft delete и ULID;
- миграции и тесты для W0.

## Следующие волны

1. W1 Security baseline: RBAC, upload security, headers, Origin check.
2. W2 Logging: structured logging, request id, PII masking, Telegram sink.
3. W3 Health and diagnostics.
4. W4 Maintenance, audit log, business events.
5. W5 DevOps safety.
6. W6 SEO core.
7. W7 SEO audit and pre-publish checklist.
8. W8 Cache invalidation and preview links.
9. W9 Media.
10. W10 Leads.
11. W11 Dev/QA and финальная документация.
