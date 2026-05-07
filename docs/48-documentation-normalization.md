# 48. Нормализация документации в формат `docs/NN-*.md`

## Назначение

Этот документ фиксирует переход к каноническому формату `docs/NN-*.md` и матрицу соответствия для исторических документов верхнего уровня `docs/*.md`.

`docs/ai/*` в процесс нормализации не входит.

## Правило канона

- Канонический источник по архитектуре, правилам, процессам и эксплуатации: `docs/NN-*.md`.
- Верхнеуровневые файлы без префикса `NN-` считаются переходным слоем совместимости до удаления.
- При расхождении приоритет всегда у `docs/NN-*.md`.

## Матрица `source -> target`

| Source | Target NN | Тип пересечения | Статус нормализации |
|---|---|---|---|
| `docs/ARCHITECTURE.md` | `docs/02-architecture.md` | полный | merged |
| `docs/MODULES.md` | `docs/06-module-architecture.md` | полный | merged |
| `docs/CI_CD.md` | `docs/35-cicd.md` | полный | merged |
| `docs/LOGGING.md` | `docs/28-logging-observability.md` | полный | merged |
| `docs/SECURITY.md` | `docs/20-security-and-access-control.md` | полный | merged |
| `docs/ROLES.md` | `docs/20-security-and-access-control.md` | полный | merged |
| `docs/UPLOAD_SECURITY.md` | `docs/25-files-and-uploads.md` | полный | merged |
| `docs/TESTING.md` | `docs/31-testing-strategy.md` | полный | merged |
| `docs/DEPLOY.md` | `docs/34-deployment.md` | полный | merged |
| `docs/STAGING.md` | `docs/34-deployment.md` | частичный | merged |
| `docs/PRODUCTION.md` | `docs/34-deployment.md` | частичный | merged |
| `docs/DEPLOY_VARIABLES.md` | `docs/27-config-and-env.md`, `docs/34-deployment.md` | частичный | merged |
| `docs/RELEASE_READINESS.md` | `docs/34-deployment.md`, `docs/37-runbooks.md` | частичный | merged |
| `docs/SEO_GUIDE.md` | `docs/26-seo-architecture.md` | полный | merged |
| `docs/REDIRECTS.md` | `docs/26-seo-architecture.md` | частичный | merged |
| `docs/FEATURES_PLAN.md` | `docs/45-roadmap-and-extension-points.md` | полный | merged |
| `docs/ROADMAP.md` | `docs/45-roadmap-and-extension-points.md` | частичный | merged |
| `docs/LOCAL_DOCKER.md` | `docs/33-local-development.md`, `docs/32-docker-architecture.md` | полный | merged |
| `docs/INSTALL.md` | `docs/33-local-development.md`, `docs/32-docker-architecture.md` | частичный | merged |
| `docs/HEALTH_CHECKS.md` | `docs/29-healthchecks.md` | полный | merged |
| `docs/LEADS.md` | `docs/14-api-area.md` | частичный | merged |
| `docs/MAINTENANCE.md` | `docs/37-runbooks.md` | частичный | merged |
| `docs/AUDIT_LOG.md` | `docs/12-admin-area.md`, `docs/28-logging-observability.md` | частичный | merged |
| `docs/BUSINESS_EVENTS.md` | `docs/28-logging-observability.md` | частичный | merged |
| `docs/SETTINGS.md` | `docs/27-config-and-env.md`, `docs/12-admin-area.md` | частичный | merged |
| `docs/ADMIN_FRONTEND.md` | `docs/22-frontend-assets.md`, `docs/12-admin-area.md` | частичный | keep (операционный guide) |
| `docs/ADMIN_GUIDE.md` | `docs/12-admin-area.md` | частичный | keep (редакторский guide) |
| `docs/CONTENT_EDITOR_GUIDE.md` | `docs/13-front-area.md`, `docs/26-seo-architecture.md` | частичный | keep (редакторский guide) |
| `docs/CONTENT_ENGINE.md` | `docs/05-domain-model.md`, `docs/14-api-area.md` | частичный | keep (feature-card) |

## Удалено после согласования

Следующие дублирующие файлы удалены после переноса контента в канонические `docs/NN-*.md`:

- `docs/ARCHITECTURE.md`
- `docs/MODULES.md`
- `docs/CI_CD.md`
- `docs/LOGGING.md`
- `docs/SECURITY.md`
- `docs/ROLES.md`
- `docs/UPLOAD_SECURITY.md`
- `docs/TESTING.md`
- `docs/DEPLOY.md`
- `docs/STAGING.md`
- `docs/PRODUCTION.md`
- `docs/DEPLOY_VARIABLES.md`
- `docs/RELEASE_READINESS.md`
- `docs/SEO_GUIDE.md`
- `docs/REDIRECTS.md`
- `docs/FEATURES_PLAN.md`
- `docs/ROADMAP.md`
- `docs/LOCAL_DOCKER.md`
- `docs/INSTALL.md`
- `docs/HEALTH_CHECKS.md`
- `docs/LEADS.md`
- `docs/MAINTENANCE.md`
- `docs/AUDIT_LOG.md`
- `docs/BUSINESS_EVENTS.md`
- `docs/SETTINGS.md`

## Что остается отдельными документами

Пока остаются отдельными, даже при наличии пересечений:

- `docs/ADMIN_FRONTEND.md`
- `docs/ADMIN_GUIDE.md`
- `docs/CONTENT_EDITOR_GUIDE.md`
- `docs/CONTENT_ENGINE.md`

Причина: у этих файлов выраженная роль пользовательских/операционных гайдов и feature-level материалов.
