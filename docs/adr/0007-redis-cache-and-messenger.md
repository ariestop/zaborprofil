# ADR-0007: Redis для cache и Doctrine для Messenger

## Статус

Accepted, 2026.

## Контекст

Проекту нужны:

- быстрый key-value cache для public страниц, settings, menu, SEO;
- надёжная очередь для async задач (email, telegram, image processing).

Redis — почти стандарт для cache и очень хорош для очередей. Однако Symfony Messenger хорошо умеет Doctrine transport, что даёт **транзакционность с БД** и упрощает backup.

## Решение

- **Cache** — Redis 8 через `cache.adapter.redis` и Symfony Cache pools (`cache.public_page`, `cache.settings`, `cache.menu`, `cache.seo`).
- **Messenger** — **Doctrine transport** (`MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0`).
- Отдельные **failed transport** на Doctrine.
- Critical alerts (Telegram) идут через async messenger handler.

## Причины

### Redis для cache

- Высокая производительность.
- Поддержка массовой инвалидации (через ключи / целевое — теги).
- Native support в Symfony Cache.
- Легко поднимается на VPS / в Docker.

### Doctrine для Messenger

- **Транзакционность.** Сообщения публикуются в той же транзакции, что и доменные изменения. Не получится опубликовать страницу и потерять `PageWasPublished` из-за падения Redis.
- **Backup.** Сообщения попадают в обычный pg_dump.
- **Простая отладка.** SQL-запрос `SELECT * FROM messenger_messages` на проде — мгновенный аудит.
- **Меньше точек отказа.** Cache может уйти, БД — нет (иначе сайт уже лежит).

## Последствия

- Производительность очереди ниже, чем у Redis transport, но для текущих задач (десятки сообщений/мин) — избыточно.
- При росте объёма сообщений можно отдельно завести Redis transport для специфических сценариев (image processing) — без отказа от Doctrine.
- Cache недоступен → degraded mode (читаем из БД), приложение работает.
- Redis не критичен для деплоя — деплой не падает, если Redis уходит.

## Конфигурация

`config/packages/cache.yaml` + `config/packages/messenger.yaml` (см. репозиторий).

В `test`: array cache + in-memory transport.

## Альтернативы

- **Redis для всего** — теряется транзакционность, нужен отдельный outbox pattern.
- **RabbitMQ / Kafka** — overkill для текущей нагрузки.
- **Только cache, без очередей** — теряем async (email block-on, telegram block-on).
- **APCu cache** — только in-process, не работает между worker’ами и web.

## Когда пересмотреть

- Объём сообщений > 1000/мин, Doctrine transport начинает отставать → переход на Redis Streams для отдельных тяжёлых каналов.
- Появится требование к delayed messages с миллисекундной точностью — Redis Streams.
- Появится cluster Redis / Sentinel.

## Связанные документы

- [23-cache-and-redis](../23-cache-and-redis.md)
- [24-messenger-and-queues](../24-messenger-and-queues.md)
- [11-infrastructure-layer](../11-infrastructure-layer.md)
