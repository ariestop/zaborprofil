# ADR-0007: Filesystem cache и Doctrine для Messenger

## Статус

Accepted, 2026. Обновлён: изначальное решение (Redis для cache) заменено по запросу заказчика — staging размещается на хостинге Beget, где доступны MySQL, но нет Redis. Решение по Messenger (Doctrine transport) не менялось. Сопутствующее решение по СУБД — [ADR-0002](0002-mysql-as-main-database.md).

## Контекст

Проекту нужны:

- быстрый key-value cache для public страниц, settings, menu, SEO;
- надёжная очередь для async задач (email, telegram, image processing).

Изначально выбирался Redis: он стандарт для cache и хорош для очередей. Однако на хостинге Beget Redis недоступен, а ставить отдельный сетевой сервис ради кэша для текущей нагрузки не оправдано. Symfony Messenger хорошо умеет Doctrine transport, что даёт **транзакционность с БД** и упрощает backup.

## Решение

- **Cache** — Symfony Cache с `cache.adapter.filesystem` (`framework.cache.app`, каталог `var/cache/<env>/pools/app`) и пулами `cache.public_page` (TTL 3600, tags), `cache.settings`, `cache.menu`, `cache.seo` (86400), построенными поверх `cache.app`. `system` — `cache.adapter.system`.
- **Sessions** — нативные PHP-сессии в файлах (`framework.session.save_path: %kernel.project_dir%/var/sessions/%kernel.environment%`).
- **Messenger** — **Doctrine transport** (`MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0`), таблица `messenger_messages` в MySQL.
- Отдельный **failed transport** тоже на Doctrine.
- Critical alerts (Telegram) идут через async messenger handler.
- Health-check `CacheCheck` (имя `cache`, label «Filesystem cache») проверяет запись/чтение файлового кэша.
- В `test`: array cache + in-memory transport.

## Причины

### Filesystem для cache

- Не требует отдельного сервиса: работает на любом хостинге, включая Beget.
- Нативная поддержка в Symfony Cache, включая теги (`TagAwareAdapter`) и TTL.
- Нет сетевых зависимостей и отдельной точки отказа; отсутствует сервис, который нужно мониторить и обновлять.
- Для текущей нагрузки (корпоративный сайт, SSR, сотни страниц) скорости локального диска (page cache ОС) достаточно.

### Doctrine для Messenger

- **Транзакционность.** Сообщения публикуются в той же транзакции, что и доменные изменения. Не получится опубликовать страницу и потерять `PageWasPublished` из-за падения отдельного брокера.
- **Backup.** Сообщения попадают в обычный `mysqldump`.
- **Простая отладка.** SQL-запрос `SELECT * FROM messenger_messages` на проде — мгновенный аудит.
- **Меньше точек отказа.** Cache может уйти, БД — нет (иначе сайт уже лежит).

## Последствия

- Кэш не разделяется между серверами и релизами: при release-based деплое у каждого релиза свой `var/cache`, после деплоя нужен прогрев; один сервер — штатная топология проекта.
- Нет pub/sub и атомарных операций уровня Redis; антиспам лидов и Idempotency-key на `cache.app` работают в рамках одного сервера (TTL соблюдается).
- Инвалидация по тегам работает через файловый tag-aware pool; физическая очистка протухших записей — `cache:pool:prune`.
- Производительность очереди ниже, чем у специализированного брокера, но для текущих задач (десятки сообщений/мин) — избыточно.
- Cache недоступен (нет прав/места на диске) → degraded mode (читаем из БД), приложение работает; проблема логируется как `cache.error`.
- Кэш не критичен для деплоя — деплой не падает при проблемах с кэшем.
- Rate limiter и lock в проекте не используются; при появлении — `symfony/lock` с `FlockStore` (файловый) или `PdoStore` (MySQL), rate limiter — на `cache.app` либо Doctrine.

## Конфигурация

`config/packages/cache.yaml` + `config/packages/messenger.yaml` + `config/packages/framework.yaml` (сессии) — см. репозиторий.

## Альтернативы

- **Redis для cache** (исходное решение) — рассмотрен и отвергнут из-за ограничений хостинга Beget (нет Redis); остаётся лучшим вариантом при росте и горизонтальном масштабировании.
- **PostgreSQL** как СУБД — отвергнут по той же причине (подробнее в [ADR-0002](0002-mysql-as-main-database.md)).
- **Redis для всего** (cache + messenger) — теряется транзакционность, нужен отдельный outbox pattern.
- **Cache в БД (Doctrine/PDO adapter)** — лишняя нагрузка на основную БД, выигрыш относительно файлов небольшой.
- **RabbitMQ / Kafka** — overkill для текущей нагрузки.
- **Только cache, без очередей** — теряем async (email block-on, telegram block-on).
- **APCu cache** — только in-process, не работает между worker’ами и web.

## Когда пересмотреть

- Появится второй веб-сервер или балансировщик → общий кэш (общая FS либо возврат к Redis).
- Хостинг/инфраструктура допускает Redis, а нагрузка требует атомарных счётчиков, rate limiting или shared locks.
- Объём сообщений > 1000/мин, Doctrine transport начинает отставать → отдельный брокер (Redis Streams и т.п.) для тяжёлых каналов.
- Появится требование к delayed messages с миллисекундной точностью.
- Кэш на диске начинает занимать неприемлемый объём или заметно деградирует I/O.

## Связанные документы

- [23-cache](../23-cache.md)
- [24-messenger-and-queues](../24-messenger-and-queues.md)
- [11-infrastructure-layer](../11-infrastructure-layer.md)
- [0002-mysql-as-main-database](0002-mysql-as-main-database.md)
