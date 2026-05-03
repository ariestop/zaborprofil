# ADR-0001: Symfony как основной фреймворк

## Статус

Accepted, 2026.

## Контекст

`zaborprofil.ru` — корпоративный сайт на WordPress с потребностью в управляемой CMS, интеграциях, расширениях и будущем e-commerce. WordPress перестал отвечать требованиям: непредсказуемые обновления плагинов, слабая типизация, размытая безопасность, ограниченная архитектура расширений.

Нужен фреймворк, который:

- активно поддерживается;
- даёт зрелую архитектуру (DI, EventDispatcher, Console, Messenger, Validator, Security, Cache);
- имеет сильное сообщество и документацию;
- удобен для production (LTS-релизы, понятный upgrade path);
- даёт хорошую производительность под FPM;
- хорошо ложится на Clean Architecture и модульный монолит;
- позволяет постепенно расширяться от CMS до e-commerce без смены платформы.

## Проблема

Без выбора единого зрелого фреймворка проект скатывается в одну из двух крайностей:

1. Самописная микро-инфраструктура — экономия в первые месяцы, экспоненциальный долг через год.
2. WordPress + плагины — повторение текущей боли в новом обличии.

Любой выбор фреймворка фиксирует команду на 5+ лет — нужно решение с предсказуемым жизненным циклом.

## Решение

Использовать **Symfony 8.x** как основной фреймворк со всеми основными компонентами:
`framework-bundle`, `security-bundle`, `messenger`, `mailer`, `validator`, `serializer`,
`twig-bundle`, `monolog-bundle`, `cache`, `console`, `form`, `property-access`, `rate-limiter`,
`uid`, `runtime`, `flex`, `dotenv` (см. [composer.json](../../composer.json)).

ORM — Doctrine 3 / DBAL 4 (см. [ADR-0004](0004-doctrine-orm-usage.md)).

## Причины

- **Зрелая архитектура.** Все нужные компоненты уже есть (`Security`, `Messenger`, `Validator`, `Serializer`, `Mailer`, `Cache`, `Console`, `Form`, `Twig`).
- **Modular monolith friendly.** DI и autowire дают чистую сборку без bundle’ов на каждый модуль.
- **Doctrine.** Лучшая ORM для PHP с поддержкой PostgreSQL JSONB, Migrations, attribute-based mapping.
- **Производительность.** OPcache + AOT cache (`cache:warmup`) + Doctrine result cache на Redis дают TTFB ≤ 200 мс для warm public_page cache.
- **LTS / upgrade.** Symfony LTS даёт долгий support; semver-совместимые upgrades с rector-рецептами.
- **Экосистема для AI-агентов.** Symfony — мейнстрим, обширная официальная документация, AI-агенты быстро ориентируются.
- **Безопасность.** Symfony Security даёт voters, firewall, CSRF, password hashing, login throttling — без необходимости писать «свой auth».
- **Альтернативы Laravel/CodeIgniter/Custom** — слабее по типизации, DI и тестируемости, либо требуют значительных собственных абстракций.

## Последствия

Положительные:

- Жёсткая привязка к Symfony компонентам в `Infrastructure` и `UI` оправдана зрелостью экосистемы.
- В `Domain`/`Application` Symfony отсутствует сознательно — слой тестируется без поднятия Kernel.
- Сильный набор готовых решений для всех будущих модулей (Lead, Catalog, Order, Partner Cabinet).
- При желании сменить фреймворк в будущем — переписывается только `UI` и `Infrastructure`, Domain и Application переносятся как есть.
- Легко нанимать разработчиков: Symfony — известный мейнстрим.

Отрицательные:

- Symfony 8 — относительно молодой major. Часть документации может опаздывать; рассчитывать на community-знание.
- Symfony deprecations нужно отслеживать в каждом minor релизе.
- DI-конфигурация требует дисциплины: легко замусорить контейнер сервисами «на всякий случай».
- Для разработчиков, привыкших к Laravel/PHP-as-a-script, кривая входа выше.

## Альтернативы

| Альтернатива | Плюсы | Минусы | Почему отклонено |
|---|---|---|---|
| **Laravel** | Быстрый старт, большое community, хорошие docs | Слабее DI, скрытая «магия» (Facades), Eloquent ActiveRecord плохо ложится на DDD, типизация менее строгая | Отклонено: проект — модульный монолит с Clean Architecture, Laravel этому противоречит. |
| **CodeIgniter** | Очень простой | Без современной DI/типизации/Doctrine | Отклонено: не tier-1 фреймворк, ограниченная экосистема. |
| **Self-built micro-framework** | Полный контроль, минимум зависимостей | Все базовые проблемы решать самим (auth, validator, mailer, queue, cache) | Отклонено: экономия первых месяцев, проигрыш на длинной дистанции. |
| **WordPress + плагины** | Готовая админка, готовые плагины | Повторение текущей боли, слабая безопасность, нестрогая типизация | Отклонено: причина миграции. |
| **Statamic / другие CMS** | Готовая CMS под коробку | Vendor lock-in, сложно расширять под e-commerce, ограниченная customization | Отклонено: не подходит под план роста до B2B/B2C/каталога. |
| **Hyperf / Swoole / RoadRunner-based** | Async PHP, высокая производительность | Незрелая экосистема, специфичный hosting, FPM на VPS гораздо проще | Отклонено: преждевременная оптимизация. |

## Компромиссы

- Привязка к Symfony в Infrastructure/UI — приемлема, потому что Domain и Application изолированы.
- Сложность DI-контейнера — приемлема, потому что autowire + минимум `services.yaml` правил.
- Расход RAM выше, чем у «голого PHP» — приемлемо, FPM-tuning решает (см. [34-deployment](../34-deployment.md)).

## Риски

| Риск | Вероятность | Импакт | Mitigation |
|---|---|---|---|
| Symfony major upgrade ломает совместимость | Средняя | Средний | Rector рецепты + LTS-стратегия + Application/Domain без зависимости от Symfony |
| Команда не поспевает за deprecations | Средняя | Низкий | Включить `phpstan-deprecation-rules` в CI; регулярный Rector run |
| DI-контейнер замусоривается | Средняя | Средний | Ревью + правило «один сервис = одна способность» (см. [02-architecture](../02-architecture.md) §«Почему Service layer должен быть осмысленным») |
| Symfony EOL и переход на 9 LTS дороже ожидаемого | Низкая | Высокий | Application/Domain без Symfony — облегчает миграцию; следить за upgrade path |
| Зависимость от bundle-экосистемы (3rd party bundles перестают развиваться) | Низкая | Средний | Минимум 3rd party bundles; всё критичное — официальные symfony/* и doctrine/* |
| Performance issue под нагрузкой | Низкая | Средний | OPcache + preload (целевое) + Redis cache + Doctrine result cache; profiling в случае инцидента |

## Когда пересмотреть решение

- Symfony 9 LTS выходит и проект готов к крупному upgrade — пересмотреть только версию, не сам выбор Symfony.
- Появляется альтернатива с сильно лучшим балансом производительности/безопасности/архитектуры (нет в обозримом будущем).
- Команда систематически не справляется с Symfony-сложностью — пересмотреть процессы (обучение, ревью), не фреймворк.
- Проект разрастается до уровня требующего multi-tenant SaaS-платформы — пересмотреть архитектуру (микросервисы), не фреймворк (Symfony работает и в микросервисах).

## Связанные документы

- [00-overview](../00-overview.md)
- [02-architecture](../02-architecture.md)
- [04-layer-rules](../04-layer-rules.md)
- [adr/0003-clean-architecture](0003-clean-architecture.md)
- [adr/0004-doctrine-orm-usage](0004-doctrine-orm-usage.md)
