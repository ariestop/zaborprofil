# 35. CI/CD

## Workflow’ы

### `.github/workflows/ci.yml`

Триггеры:

- push в `main`/`develop`/`feature/**`/`fix/**`;
- pull_request в `main`/`develop`.

Концепт `concurrency: ci-${{ github.ref }}; cancel-in-progress: true` — отменяет старый CI при пуше нового коммита.

#### Backend job

Сервисы:

- `postgres:18`
- `redis:8`

Шаги:

1. Checkout.
2. Setup PHP 8.5 (`shivammathur/setup-php@v2`) + extensions `ctype, iconv, intl, mbstring, opcache, pdo_pgsql, redis`.
3. `composer validate --strict`.
4. `composer install --prefer-dist`.
5. `composer audit`.
6. `composer check:syntax` (php-lint).
7. `composer check:cs` (php-cs-fixer dry-run).
8. `composer check:phpstan`.
9. `composer check:rector` (dry-run).
10. `cp .env.test.ci .env.test.local` — переключает тесты на Postgres.
11. `doctrine:migrations:status --env=test`.
12. `doctrine:migrations:migrate --env=test --allow-no-migration`.
13. `doctrine:schema:validate --env=test --skip-sync`.
14. `lint:container --env=test`.
15. `lint:twig templates --env=test`.
16. Bash syntax check для `tools/deploy/*.sh`.
17. `php bin/console app:smoke:test --env=test`.
18. `composer test` (PHPUnit).

#### Frontend job

1. Checkout.
2. Setup Node 25.9.0.
3. `npm ci`.
4. `npm audit --audit-level=high`.
5. `npm run typecheck`.
6. `npm run test:frontend`.
7. `npm run lint:admin`.
8. `npm run build`.
9. `npm run check:chunks`.

#### E2E Smoke job

Дополнительно запускается `E2E Smoke` job:

1. Поднимает Postgres/Redis services.
2. Применяет migrations в `test` env.
3. Создаёт e2e admin user через `tools/testing/seed-e2e-admin.php`.
4. Собирает frontend.
5. Поднимает встроенный PHP server (`php -S`) на `127.0.0.1:8000`.
6. Устанавливает Chromium (`npx playwright install --with-deps chromium`).
7. Запускает `npm run test:e2e:smoke` (happy-path + mutation smoke + negative 422 contract check).
8. При падении публикует Playwright artifacts (`test-results`, `playwright-report`, app server log).

### `.github/workflows/deploy.yml`

Триггеры:

- push в `develop` или `staging` для staging-потока;
- tag `v*` для production-потока через staging gate;
- workflow_dispatch для повторного staging deploy и операционных сценариев.

Concurrency: `deploy-${{ github.ref }}; cancel-in-progress: false`.

#### Job `verify-ci`

Гейт: на текущем SHA должен быть успешный run workflow’а `CI`. Если нет — deploy отказывается.

#### Job `deploy-staging`

- Environment: `staging`.
- Срабатывает при push в `develop`/`staging`, а также как первый шаг tag-based production pipeline.
- SCP заливает `tools/deploy/*` на staging-сервер в `/tmp/zaborprofil-deploy`.
- SSH запускает `deploy-staging.sh` с переменными окружения из `secrets.STAGING_*`.
- После успешного health-check staging script запускает `staging-smoke.sh`.

#### Job `deploy-production`

- Environment: `production`.
- Срабатывает для `v*` tags после успешного staging job и environment approval.
- Зависит от `deploy-staging`.
- SCP + SSH + `deploy-production.sh` с `CONFIRM_STAGING_DEPLOYED=yes` и `CONFIRM_DEPLOY_SAFETY_CHECKLIST=yes`.

Production intentionally does not run as an unguarded manual path: сначала staging, затем approval и только после этого production.

## Secrets

| Secret | Где |
|---|---|
| `STAGING_SSH_HOST`, `STAGING_SSH_USER`, `STAGING_SSH_KEY`, `STAGING_SSH_PORT` | `staging` environment |
| `PRODUCTION_SSH_HOST`, `PRODUCTION_SSH_USER`, `PRODUCTION_SSH_KEY`, `PRODUCTION_SSH_PORT` | `production` environment |
| `STAGING_BRANCH`, `STAGING_APP_ROOT`, `STAGING_HEALTH_URL` | env vars (могут быть в repo vars) |
| `PRODUCTION_BRANCH`, `PRODUCTION_APP_ROOT`, `PRODUCTION_HEALTH_URL` | env vars |

GitHub Environments дают:

- ручное approval для production (целевое — включить);
- ограничение branch protection (только `main`).

## Что CI проверяет автоматически

- Composer validity.
- Composer security audit.
- PHP синтаксис.
- PHP-CS-Fixer compliance.
- PHPStan.
- Rector dry-run.
- Doctrine migrations applicable + schema in sync.
- Symfony container lint.
- Twig lint.
- Deploy scripts syntax.
- PHPUnit (Postgres).
- `app:smoke:test` для базовой release readiness.
- npm audit.
- Frontend typecheck как отдельный quality gate.
- Frontend unit tests (`test:frontend`).
- Scoped admin lint (`lint:admin`).
- npm/Vite build.
- Budget guardrail по критичным admin chunks (`check:chunks`).
- Browser smoke regression admin-flow (`test:e2e:smoke`).

## Что НЕ автоматизировано (целевое)

- Phpat / deptrac — статические границы слоёв.
- Lighthouse / SEO checks на staging.
- Visual regression (Playwright/Percy) для admin/public.
- Mutation testing.
- Coverage report c пороговыми значениями.
- Container image scanning (если перейдём на Docker prod).

## Ручной workflow_dispatch

Полезно для:

- повторного деплоя того же commit;
- релиза без push (например, пересобрать с тем же кодом);
- exceptional fix.

## Branch strategy

| Branch | Назначение |
|---|---|
| `main`/`master` | стабильная база |
| `develop` | интеграция фич, авто-deploy на staging |
| `staging` | альтернативная ветка для staging тестов |
| `feature/*` | feature branches, ревью через PR |
| `hotfix/*` | срочные фиксы напрямую от main с тегом v* |
| tag `v*` | production release |

## Релизные теги

`vMAJOR.MINOR.PATCH` (semver).

- MAJOR — breaking changes (редко в CMS).
- MINOR — фичи backward-compatible.
- PATCH — bugfix.

Тег создаётся через `git tag v0.5.3 && git push --tags`. Это запускает production deploy.

## Чек-лист изменения CI/CD

- [ ] Изменение протестировано на feature branch.
- [ ] Если меняется secret — обновлены оба environments (staging/prod).
- [ ] Логика gating CI green сохранена.
- [ ] Документация обновлена в `docs/NN-*.md` (минимум этот файл + [34-deployment](34-deployment.md), если менялся deploy-процесс).
- [ ] Не понижены проверки качества (cs/phpstan/rector/phpunit).

## Anti-patterns

- Skip CI через `[skip ci]` в коммите.
- Деплой без CI green.
- Хардкод секретов в YAML.
- Branch protection отключена для main.
- `workflow_dispatch` без environment protection — даёт любому maintainer задеплоить.

## Связанные документы

- [34-deployment](34-deployment.md)
- [38-coding-standards](38-coding-standards.md)
- [34-deployment](34-deployment.md)
