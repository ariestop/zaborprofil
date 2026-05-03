# Легаси-документация (`docs/legacy/`)

Эти документы оставлены для совместимости с внешними ссылками (commit-сообщения, тикеты, закладки, переписка). **Каноническим источником они НЕ являются.**

Канонический источник по каждой теме — нумерованный документ в `docs/`. См. таблицу ниже.

> При расхождении приоритет имеет нумерованный документ. Если в легаси есть полезная деталь, отсутствующая в нумерованных документах — перенесите её туда и обновите соответствующий `docs/NN-*.md` в том же PR (см. правила в [../38-coding-standards.md](../38-coding-standards.md#документация--обязательная-актуализация-в-том-же-pr)).

## Карта legacy → канонический документ

| Legacy | Канонический источник |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | [../02-architecture.md](../02-architecture.md) |
| [MODULES.md](MODULES.md) | [../06-module-architecture.md](../06-module-architecture.md) |
| [INSTALL.md](INSTALL.md), [LOCAL_DOCKER.md](LOCAL_DOCKER.md) | [../33-local-development.md](../33-local-development.md), [../32-docker-architecture.md](../32-docker-architecture.md) |
| [DEPLOY.md](DEPLOY.md), [STAGING.md](STAGING.md), [PRODUCTION.md](PRODUCTION.md), [DEPLOY_VARIABLES.md](DEPLOY_VARIABLES.md) | [../34-deployment.md](../34-deployment.md) |
| [CI_CD.md](CI_CD.md) | [../35-cicd.md](../35-cicd.md) |
| [SECURITY.md](SECURITY.md), [ROLES.md](ROLES.md), [UPLOAD_SECURITY.md](UPLOAD_SECURITY.md) | [../20-security-and-access-control.md](../20-security-and-access-control.md), [../25-files-and-uploads.md](../25-files-and-uploads.md) |
| [CONTENT_ENGINE.md](CONTENT_ENGINE.md), [CONTENT_EDITOR_GUIDE.md](CONTENT_EDITOR_GUIDE.md), [ADMIN_GUIDE.md](ADMIN_GUIDE.md), [ADMIN_FRONTEND.md](ADMIN_FRONTEND.md) | [../12-admin-area.md](../12-admin-area.md), [../13-front-area.md](../13-front-area.md), [../22-frontend-assets.md](../22-frontend-assets.md) |
| [SEO_GUIDE.md](SEO_GUIDE.md), [REDIRECTS.md](REDIRECTS.md) | [../26-seo-architecture.md](../26-seo-architecture.md) |
| [SETTINGS.md](SETTINGS.md) | (точечный референс по Settings; см. [../06-module-architecture.md](../06-module-architecture.md)) |
| [LOGGING.md](LOGGING.md) | [../28-logging-observability.md](../28-logging-observability.md) |
| [TESTING.md](TESTING.md) | [../31-testing-strategy.md](../31-testing-strategy.md) |
| [ROADMAP.md](ROADMAP.md), [FEATURES_PLAN.md](FEATURES_PLAN.md) | [../45-roadmap-and-extension-points.md](../45-roadmap-and-extension-points.md) |

## Когда удалять документы из этой папки

После того как:

1. Вся уникальная информация из легаси-документа перенесена в канонический `docs/NN-*.md`;
2. На внешних площадках (Slack, тикеты, README на staging) ссылки переписаны;
3. Прошло разумное время (например, 2–3 спринта) без сообщений о «нашёл устаревший документ».

Удаление — отдельный PR с пометкой «docs cleanup».
