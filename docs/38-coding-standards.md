# 38. Coding standards

## Язык и версии

- PHP `>=8.5`. Используются: readonly properties, `final readonly class`, intersection / union types, enums, first-class callable, `#[Override]` (целевое 8.4+).
- TypeScript `>=5.8`. `strict: true`.
- Node `>=25.9.0`.

## Стиль PHP

`php-cs-fixer` (`.php-cs-fixer.php` целевое) — следует @PER-CS / @Symfony rule set.

Жёсткие правила:

- `declare(strict_types=1);` в начале **каждого** PHP-файла.
- `final class` по умолчанию (для не-Doctrine; Doctrine entities — `final` тоже допустим для `App\Module\*\Domain\Entity`, потому что Doctrine 3 поддерживает).
- `private` поля, `public` — только если есть бизнес-смысл.
- Конструктор property promotion (`__construct(private readonly Foo $foo)`).
- `readonly` где возможно.
- Никаких `mixed`, `array` без `@var` / generic-style phpdoc.
- Имя класса = имя файла (PSR-4).
- Namespace: `App\Module\<Module>\<Layer>\...`, `App\Shared\<Layer>\...`.

## Naming

| Сущность | Конвенция |
|---|---|
| Класс | `UpperCamelCase` |
| Метод | `camelCase` |
| Поле | `camelCase` |
| Константа | `UPPER_SNAKE_CASE` (но для enum — `Case` PascalCase) |
| Переменная | `camelCase` |
| Тестовый метод | `testItDoesSomething` или `it_does_something` |
| Route name | `<area>_<resource>_<action>` |
| Cache key | `<resource>.<id>` |
| Migration | `Version<YYYYMMDDHHMMSS>` |

## Файловая структура PHP

```php
<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\CreatePageCommand;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;

final class CreatePageHandler
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {
    }

    public function __invoke(CreatePageCommand $command): PageOutput
    {
        // ...
    }
}
```

## Phpstan

`vendor/bin/phpstan analyse` — обязателен в CI. Целевое: level 9.

Правила:

- Без подавления (`@phpstan-ignore`) без обоснования в комментарии.
- `mixed` запрещён в публичных контрактах.
- `iterable<KeyT, ValueT>` для коллекций.

## Rector

`vendor/bin/rector process --dry-run` — обязателен в CI.

Используются базовые sets PHP 8.5 / Symfony / Doctrine.

## Comments

- Комментарии — только если объясняют **почему**, а не **что**.
- Не оставлять `// TODO` без issue-ссылки.
- Не оставлять закомментированный код.
- DocBlock — только для generic types (`@var array<string, Foo>`) и для `@throws`.

## Imports / use

- Один use per type.
- Не использовать алиасы без причины.
- Sorting — Symfony / `php-cs-fixer` стандарт (alphabetical).

## Error handling

См. [30-error-handling](30-error-handling.md).

- Не глотать `\Throwable` без логирования.
- Domain exceptions — наследники `\DomainException`.
- HTTP exceptions — только в UI.

## Testing

См. [31-testing-strategy](31-testing-strategy.md).

- Тесты обязательны для нового кода.
- Coverage минимум 70% для критичных модулей (целевое — измерять и поднимать).

## Twig

- Двойные кавычки только для интерполяции.
- Auto-escape всегда включён.
- `|raw` запрещён без обоснования.
- Переменные из контроллера — view models, не Doctrine entity напрямую (там, где есть смысл).

## TypeScript / React

- `strict: true`.
- Файлы UI-компонентов админки — `*.tsx`.
- ESLint + Prettier (целевое — пока через `tsc --noEmit`).
- API клиент типизирован, не `any`.

## Имена коммитов

`<type>(<scope>): <subject>`:

- `feat(content): add page reorder endpoint`
- `fix(seo): respect 302 redirects`
- `chore(deps): bump symfony to 8.1.x`
- `docs(deploy): add prod runbook`
- `refactor(auth): extract AdminUserChecker tests`
- `test(content): cover archive flow`

`type` ∈ `feat`, `fix`, `refactor`, `chore`, `docs`, `test`, `perf`, `ci`.

## PR checklist

- [ ] Один логический change.
- [ ] CI зелёный.
- [ ] Документация обновлена (если затронут публичный контракт / схема).
- [ ] Тесты добавлены / обновлены.
- [ ] Не нарушены layer rules ([04-layer-rules](04-layer-rules.md)).
- [ ] Нет закомментированного кода / `var_dump`.
- [ ] Нет утечки секретов в diff.

## Что запрещено

- `var_dump`, `print_r`, `dump()` в production коде.
- `die`, `exit` (кроме `bin/console` и явных entrypoints).
- `eval` категорически.
- `extract($_REQUEST)` и подобное.
- Кириллица в идентификаторах кода (только в строках/комментариях, по делу).
- Hardcoded URL/credentials.
- Регулярные выражения с `e` modifier.

## Документация — обязательная актуализация в том же PR

Любое изменение, **меняющее фактическое поведение проекта**, должно сопровождаться обновлением соответствующего нумерованного документа в `docs/` **в том же PR**, а не «как-нибудь потом». Это — обязательное правило, проверяется ревьюером.

| Что изменилось в коде | Какой документ обязательно обновить |
|---|---|
| Новое/удалённое поле Entity, новая Entity | [05-domain-model.md](05-domain-model.md) (таблицы полей + ER), [17-doctrine-and-database.md](17-doctrine-and-database.md) (если меняется индекс/constraint) |
| Новая Doctrine миграция | [18-migrations.md](18-migrations.md) (если меняется паттерн миграции, не просто добавление файла) |
| Новый Application handler / Command / Query | [09-application-layer.md](09-application-layer.md) (только если меняется паттерн) |
| Новый контроллер / route / маршрут с приоритетом | [08-controller-architecture.md](08-controller-architecture.md), [16-routing.md](16-routing.md), [12-/13-/14-/15-area.md](12-admin-area.md) (по зоне) |
| Новый Twig partial / шаблон / view-data | [21-templates-and-twig.md](21-templates-and-twig.md), [13-front-area.md](13-front-area.md) |
| Изменение SEO-рендера, sitemap, robots, redirects | [26-seo-architecture.md](26-seo-architecture.md), при необходимости [13-front-area.md](13-front-area.md) |
| Изменение security/firewall/voter/access_control | [20-security-and-access-control.md](20-security-and-access-control.md) |
| Изменение кэш-пула / инвалидации | [23-cache-and-redis.md](23-cache-and-redis.md) |
| Новый Messenger handler / message | [24-messenger-and-queues.md](24-messenger-and-queues.md) |
| Новый upload-флоу | [25-files-and-uploads.md](25-files-and-uploads.md) |
| Изменение `.env` / `config/packages/*.yaml` | [27-config-and-env.md](27-config-and-env.md), при необходимости связанный nn-* документ |
| Новый healthcheck | [29-healthchecks.md](29-healthchecks.md) |
| Новый exception класс / формат ошибки | [30-error-handling.md](30-error-handling.md) |
| Изменение CI/CD пайплайна | [35-cicd.md](35-cicd.md) |
| Изменение деплой-скриптов / systemd unit / nginx config | [34-deployment.md](34-deployment.md), при необходимости [37-runbooks.md](37-runbooks.md) |
| Архитектурное решение (новая граница, новая зависимость, новый компромисс) | Создать новый ADR в `docs/adr/` (см. шаблон в [41-implementation-playbook.md](41-implementation-playbook.md)) |
| Новый модуль `src/Module/<X>` | [06-module-architecture.md](06-module-architecture.md) (карта модулей), [43-module-development-guide](43-module-development-guide.md), `src/Module/<X>/README.md` |
| Изменение публичного контракта модуля (interface) | `src/Module/<X>/README.md`, [06-module-architecture.md](06-module-architecture.md) |

**Правила формулировки в обновлённом документе:**

- Если новое — это уже работает в коде и протестировано → раздел/таблица/абзац идёт без префикса либо с «Фактическое состояние».
- Если новое — это план, для которого пока нет реализации → явно «Целевое состояние», «Целевое», «Планируется», «Не реализовано» или «Требует внедрения».
- Если код реализует часть документа, а часть остаётся целевой → разделить раздел на «Факт» / «Целевое» (как сделано в 26-seo-architecture после аудита).

**Ревью-чек-лист:**

- [ ] В PR есть изменения в `docs/` соответствующих изменённому коду слоёв.
- [ ] Раздел документа явно отражает новое состояние, без расхождения с кодом.
- [ ] Если код заявляет факт, а в документе осталось «целевое» — поправить документ.
- [ ] Если архитектурное решение нетривиальное — создан ADR.

## Целевые улучшения

- Phpat / deptrac — статическая проверка границ слоёв.
- Lint pre-commit hook.
- Mutation testing.
- `tools/quality/docs-coverage.php` — линтер «доки vs код» (см. [45-roadmap-and-extension-points.md](45-roadmap-and-extension-points.md)).

## Связанные документы

- [04-layer-rules](04-layer-rules.md)
- [09-application-layer](09-application-layer.md)
- [10-domain-layer](10-domain-layer.md)
- [31-testing-strategy](31-testing-strategy.md)
- [40-cursor-rules](40-cursor-rules.md)
