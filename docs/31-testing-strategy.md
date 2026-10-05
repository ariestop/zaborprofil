# 31. Testing strategy

## Стек

- PHPUnit 12.
- `symfony/browser-kit` + `symfony/css-selector` для functional.
- `symfony/phpunit-bridge` для интеграции.
- Playwright (`@playwright/test`) для browser smoke E2E admin-flow.
- (целевое) `infection/infection` для mutation testing.

## Пирамида

```mermaid
flowchart TB
    Unit[Unit tests<br/>~70% объёма] --> Integration[Integration tests<br/>~25%]
    Integration --> Functional[Functional tests<br/>~5%]
```

| Тип | Где | Что покрывает |
|---|---|---|
| Unit | `tests/Unit/<Module>/...` | Domain entities, enums, value objects, application handlers с моками |
| Integration | `tests/Integration/<Module>/...` | Doctrine repositories на тестовой БД, listeners, subscribers |
| Functional | `tests/Functional/<Module>/...` | HTTP контроллеры через `WebTestCase` |
| E2E сценарии | `tests/E2E/...`, `tests/e2e/...` | ручные чек-листы + browser smoke/regression для admin runtime |
| Support | `tests/Support/...` | хелперы (`SchemaTestHelper`) |

## Naming

- Класс: `<UnderTest>Test`.
- Метод: `test<Behaviour>` или `it_<does_thing>` (snake-style — допустимо, но единообразно).
- Покрывайте «happy path», «edge case», «failure case».

## Структура папок

```text
tests/
├── Unit/
│   ├── Content/PageTest.php
│   ├── Auth/Infrastructure/Security/AdminPermissionVoterTest.php
│   ├── Settings/Application/Service/SettingsServiceTest.php
│   └── Shared/Logging/PiiRedactorProcessorTest.php
├── Integration/
│   └── Content/DoctrineContentRepositoryTest.php
├── Functional/
│   ├── HealthCheckTest.php
│   ├── AdminLoginTest.php
│   ├── Content/AdminContentApiTest.php
│   ├── Seo/{Robots,Sitemap,Redirect}ControllerTest.php
│   └── Security/SecurityHeadersTest.php
├── Support/
│   └── Database/SchemaTestHelper.php
└── bootstrap.php
```

## Test database

В `phpunit.xml` (или через `.env.test`):

- `DATABASE_URL` — отдельная БД.
- Локальный PHPUnit запускается только против MySQL service `mysql` из Docker Compose (БД `zaborprofil_test`, создаётся `make test-db`).
- SQLite (`sqlite://...`) запрещён для агентов и локальных quality-прогонов.
- В CI — сервис `mysql:8.4` (`.env.test.ci` подменяет `.env.test.local`).

## Schema setup

`SchemaTestHelper`:

- На старте functional/integration теста удаляет/создаёт схему и применяет миграции.
- В MySQL DDL неявно коммитится, поэтому откат теста транзакцией для схемы невозможен: хелпер удаляет все таблицы (`DROP TABLE IF EXISTS ...`) и создаёт схему заново через `SchemaTool`; `dropDatabase()` не используется. Guard: имя БД должно оканчиваться на `_test`.

## Fixtures

В проекте `doctrine/doctrine-fixtures-bundle` пока **не подключён**. Использовать:

- builder’ы (`PageBuilder::default()->withPath('/abc')->build()`) — целевое, чисто Domain.
- Прямой `entityManager->persist()` в тесте.

## Mocking

| Можно мокать | Нельзя мокать |
|---|---|
| `*RepositoryInterface` | `EntityManagerInterface` |
| `LoggerInterface` | Doctrine `Connection` |
| `MessageBusInterface` | `Page`, `PageBlock` (это value, легче создать) |
| `CacheInterface` | `Ulid` |
| `ClockInterface` (целевое) | `\DateTimeImmutable` |

PHPUnit `createMock`/`createStub` — для interface, не для конкретных классов.

## Что обязательно тестировать

- Все domain methods, меняющие state.
- Все enum методы.
- Все application handlers (с моками).
- Все controllers (functional на 200/4xx/5xx).
- Все voters/user checkers.
- Все subscribers.
- Migration smoke (через CI `migrations:status`).
- Все public Twig extensions (unit).

## Что НЕ нужно тестировать

- Тривиальные геттеры без логики (если есть бизнес-смысл — да).
- Symfony framework сам по себе.
- Сторонние библиотеки.
- DTO без логики (если конструктор не делает валидаций — нет).

## Smoke tests

Functional тесты на `/health`, `/sitemap.xml`, `/robots.txt`, `/admin/login` — обязательны. После любых изменений infrastructure они должны проходить.

Для admin runtime дополнительно обязательны regression smoke-пути:

- `/admin/pages` -> `/admin/pages/new` -> `/admin/pages/{id}` (вкладки «Контент и блоки», «SEO», «Настройки», «Ревизии»); старый URL `/admin/pages/{id}/builder` остаётся рабочим;
- сохранение SEO/настроек, предупреждение при уходе с несохранёнными правками;
- builder save/reorder/rich-text update;
- preview-link generation для страницы.
- negative API contract smoke: невалидный payload возвращает `422` с validation details.

Текущий Playwright слой организован через helper-модули:

- `tests/e2e/helpers/admin.ts` — login + API mutations + runtime assertions;
- `tests/e2e/helpers/selectors.ts` — централизованные UI selectors/labels;
- `tests/e2e/helpers/fixtures.ts` — генерация deterministic payloads и уникальных PNG (медиатека дедуплицирует файлы по хешу).

Набор E2E минимален и держится в двух файлах:

- `tests/e2e/admin-smoke.spec.ts` — регрессия админ-интерфейса (редактор страницы, builder, SEO-панель, медиатека, контракт 422);
- `tests/e2e/content-to-lead.spec.ts` — сквозной сценарий: создание страницы -> блок -> SEO-поля -> загрузка картинки через MediaPicker и выбор её как og:image -> публикация -> публичная страница (title, description, og:image, h1) -> заявка посетителя через публичную форму -> заявка в CRM со статусом «Новая» -> «Взять в работу».

## Матрица ролей и граничные случаи API

- `tests/Functional/Security/AdminRoleMatrixTest.php` — таблица «роль × эндпоинт» для `SUPER_ADMIN`, `ADMIN`, `EDITOR`, `SEO`, `MANAGER`. Ожидаемые права записаны в тесте независимо от `AdminPermissionVoter`, поэтому любое изменение прав требует осознанной правки таблицы. Деструктивные эндпоинты (очистка кэша, перезапуск процессов, режим обслуживания, миграции) проверяются только на отказ. Отдельный тест проверяет, что анонимный пользователь не получает доступ ни к одному маршруту `/admin/*`, в том числе к новым.
- `tests/Functional/Media/AdminMediaEdgeCasesTest.php` и `tests/Unit/Shared/Upload/UploadValidatorLimitsTest.php` — небезопасные и повреждённые файлы, размер и размеры изображения, двойное расширение, обход пути в имени, лимиты метаданных, границы пагинации.
- `tests/Functional/Lead/LeadAdminEdgeCasesTest.php` — пагинация и фильтры, все переходы статусов, типы входных данных, границы заметок, назначение ответственного, CSV-выгрузка.

Когда в проект добавляется новый маршрут `/admin/api/*`, его нужно добавить в `endpoints()` матрицы ролей.

## Пороги покрытия

- PHP: `composer test:coverage` (PHPUnit с `pcov`, Clover-отчёт `var/coverage/clover.xml`) и `composer check:coverage` — порог покрытия строк 75% (`tools/quality/check-coverage.php`). Фактическое значение на момент введения — около 81%.
- Frontend: `npm run test:frontend:coverage` (Vitest + `@vitest/coverage-v8`); пороги в `vitest.config.ts` — statements/lines 30%, functions 50%, branches 70%.

Пороги — защита от падения покрытия, а не цель. Повышайте их вместе с ростом покрытия, не снижайте без обсуждения.

## SEO tests (целевое)

- canonical присутствует на каждой публичной странице;
- robots в prod-конфиге не `Disallow: /`;
- sitemap содержит published без drafts;
- 301 при смене path.

## Запуск

```bash
vendor/bin/phpunit
make test
make smoke          # release-readiness smoke checks
make quality        # validate + syntax + cs + phpstan + rector + schema/lint + phpunit + smoke + npm build
npm run typecheck
npm run test:frontend
npm run lint:admin
npm run test:frontend:coverage
npm run test:e2e:smoke   # только smoke
npm run test:e2e         # smoke + сквозной сценарий
composer test:coverage && composer check:coverage
```

В CI — `composer test` (см. composer.json scripts).

Для полного локального прогона качества:

```bash
make up
make test-db
make quality
npm run test:frontend
```

## Что проверять перед merge

- [ ] `composer check:syntax` ok.
- [ ] `composer check:cs` ok.
- [ ] `composer check:phpstan` ok.
- [ ] `composer check:rector` ok.
- [ ] `composer test` ok.
- [ ] Schema validate ok.
- [ ] `lint:container`, `lint:twig` ok.
- [ ] `php bin/console app:smoke:test` ok.
- [ ] `npm run typecheck` ok.
- [ ] `npm run test:frontend` ok.
- [ ] `npm run lint:admin` ok.
- [ ] `npm run test:e2e` ok.
- [ ] `composer check:coverage` и `npm run test:frontend:coverage` ok.
- [ ] `npm run build` ok.
- [ ] `npm run check:chunks` ok.

## Что проверять при новой фиче

- [ ] Unit-тест на domain entity / enum / value object.
- [ ] Unit-тест на application handler.
- [ ] Integration-тест на repository, если новый.
- [ ] Functional-тест на новый endpoint (200 + edge-cases 401/403/404/422).
- [ ] Subscriber/listener покрыт тестом.
- [ ] Logging покрыт (если критично).
- [ ] Edge case: empty input, big input, invalid input.

## Anti-patterns

- Тесты, проверяющие имена методов через рефлексию.
- Тесты, читающие prod-БД.
- Тесты, делающие сетевые запросы наружу.
- Тесты, зависящие от текущего времени без `ClockInterface` мока.
- 1000-строчный test class на всё подряд.
- Тесты, которые проходят локально и ломаются в CI без ясного reason (timezone, locale).

## Связанные документы

- [09-application-layer](09-application-layer.md)
- [10-domain-layer](10-domain-layer.md)
- [11-infrastructure-layer](11-infrastructure-layer.md)
- [38-coding-standards](38-coding-standards.md)
- [38-coding-standards](38-coding-standards.md)
