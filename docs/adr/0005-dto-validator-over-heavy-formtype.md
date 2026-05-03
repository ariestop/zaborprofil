# ADR-0005: DTO + Validator вместо тяжёлых FormType

## Статус

Accepted, 2026.

## Контекст

Symfony FormType — мощный инструмент для HTML-форм с CSRF, themes, error rendering. Однако:

- основная админка — Vue 3 SPA, общающаяся через JSON API;
- большинство input’ов — JSON, не HTML;
- FormType создаёт связку контроллер ↔ Form ↔ Entity, которая склонна тянуть бизнес-логику в Form;
- Form-классы плохо ложатся на Clean Architecture (требуют Symfony окружения, тяжело тестируются).

## Решение

По умолчанию использовать **DTO + Symfony Validator**:

- Input DTO — `final readonly class` с Validator constraints.
- Парсинг JSON → DTO в контроллере (через `JsonRequest`/`Serializer`).
- Validator вызывается до Application handler.
- Output DTO — для admin/public API.

**FormType** допустим только для:

- HTML-форм с CSRF, где SPA не используется (login, простые public-формы);
- сложных HTML-форм с file upload + nested collections, где Form-themes реально упрощают код.

## Причины

- **Меньше связанности.** DTO не знает Symfony HTTP, проще тестировать.
- **Чистые слои.** Domain/Application работают с типизированным DTO.
- **API-first.** Большинство input’ов JSON; FormType для JSON — overkill.
- **Стабильность типов.** `readonly` поля + строгие типы.
- **Поддерживает Clean Architecture.** Validator constraints живут на DTO, не на Entity.

## Последствия

- Контроллеру нужен парсер JSON → DTO. Сейчас точечно `JsonRequest`; целевое — общий `JsonRequestParser`.
- Тесты на validator constraints — отдельные unit-тесты на DTO.
- Шаблоны admin login и подобные — остаются на FormType.
- Нет авто-генерируемого error rendering из FormType — JSON ответы строятся вручную через `ContentApiResponder`.

## Альтернативы

- **FormType везде** — плохая совместимость с SPA + JSON, грязные слои.
- **Только массивы + ручная валидация** — потеря строгой типизации.
- **API Platform** — мощно, но навязывает архитектуру и тяжелее, чем нужно проекту сейчас.

## Когда пересмотреть

- Если появится много сложных HTML-форм без SPA (маловероятно).
- Если появится gRPC / typed RPC — пересмотреть в сторону code-gen DTO.

## Связанные документы

- [19-forms-dto-validation](../19-forms-dto-validation.md)
- [09-application-layer](../09-application-layer.md)
- [08-controller-architecture](../08-controller-architecture.md)
