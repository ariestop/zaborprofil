# ADR-0003: Clean Architecture + Modular Monolith

## Статус

Accepted, 2026.

## Контекст

Проект должен:

- развиваться годами без превращения в legacy;
- держать чёткие границы между UI, бизнес-логикой и инфраструктурой;
- поддерживать модульный рост (Lead, Catalog, Order, Partner и т.д.) без распада на микросервисы;
- быть тестируемым на уровне Domain без Symfony Kernel.

Стандартный Symfony-проект часто превращается в "fat controllers / fat services / fat entities", где бизнес-логика расползается. Clean Architecture даёт способ удерживать это.

## Решение

Принять **Clean Architecture внутри Modular Monolith** как фундаментальный архитектурный стиль.

- 4 слоя: `Domain` → `Application` → `Infrastructure` (реализует контракты) → `UI`.
- Modular Monolith: каждый bounded context живёт в `src/Module/<Name>` с собственными слоями.
- `src/Shared/` — общая инфраструктура и контракты.
- Жёсткие правила зависимостей (см. [04-layer-rules](../04-layer-rules.md)).

## Причины

- **Тестируемость.** Domain тестируется чистым PHPUnit без Kernel.
- **Независимость от фреймворка.** Замена Symfony потребует переписать только UI и Infrastructure.
- **Локализация изменений.** Изменения в каталоге не ломают админку Page.
- **Понятная структура для AI-агентов.** Чёткие правила — меньше деградаций.
- **Modular Monolith vs Microservices.** Один deployment, одна транзакция, никаких проблем eventual consistency для одной команды.

## Последствия

- Больше бойлерплейта (DTO, handler, interface) против "тонких" Symfony-контроллеров.
- Нужны жёсткие code review и static analysis (PHPStan, целевое — phpat/deptrac).
- Domain не использует Symfony Validator constraints на самих entity (constraints — на DTO).
- Repository — interface в Domain + реализация в Infrastructure.
- Бизнес-логика **не** живёт в Twig, Controller, EventSubscriber.

## Альтернативы

- **Symfony Best Practices "as is"** — без чётких слоёв, со временем превращается в legacy.
- **Hexagonal без Modular Monolith** — без явного разделения на bounded contexts проект становится "одной большой кодобазой".
- **Микросервисы** — преждевременная сложность для команды и нагрузки.

## Когда пересмотреть

- Команда в 1 человека и проект перестал расти — упрощать (но layer rules всё равно полезны).
- Один из модулей перерос в самостоятельный продукт — разделить.
- Появилась потребность в технологически разнородных частях (например, ML-сервис на Python) — выделить как отдельный сервис, не как другой модуль.

## Связанные документы

- [02-architecture](../02-architecture.md)
- [04-layer-rules](../04-layer-rules.md)
- [06-module-architecture](../06-module-architecture.md)
