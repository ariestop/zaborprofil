# ADR-0008: Docker только для локальной разработки

## Статус

Accepted, 2026.

## Контекст

Команды разработки имеют разное окружение (macOS, Windows + WSL2, Linux). Без воспроизводимого setup’а легко получить:

- разные версии PHP/Postgres/Redis;
- conflicts с system packages;
- "работает у меня" сценарии;
- сложности при онбординге новичков.

В то же время, production VPS — это конкретный сервер с конкретными системными пакетами. Запускать Docker на проде = добавлять оверхед без выигрыша.

## Решение

- **Docker + Docker Compose** для локальной разработки и CI-смежных сценариев.
- **Не использовать Docker** для staging и production (см. [ADR-0009](0009-vps-deployment-strategy.md)).
- Версии образов жёстко зафиксированы и совпадают с production:
  - `php:8.5-fpm-bookworm`,
  - `nginx:1.30.0-alpine`,
  - `postgres:18`,
  - `redis:8-alpine`,
  - `node:25.9.0-bookworm`.

## Причины

### Docker для local

- **Один command setup.** `make build && make up`.
- **Версии runtime соответствуют prod.** Локально Postgres 18 — на prod Postgres 18.
- **Изоляция.** Не ломает system PHP/Postgres.
- **Mailpit/Adminer как dev-инструменты** не разворачиваются на prod, не загрязняют систему.

### Не Docker для prod

- **Простота VPS.** systemd + nginx + php-fpm — минимум слоёв, проще диагностика.
- **Производительность.** Без overhead Docker network на FastCGI.
- **Тонкая настройка.** PostgreSQL/Redis лучше управляются на голом VPS.
- **Backup проще.** `pg_dump` напрямую.
- **Меньше cost.** Малые VPS лучше переносят native стек.

## Последствия

- Риск расхождения dev ↔ prod. Mitigation:
  - Версии образов жёстко закреплены в `docker-compose.yml` и в deploy-документации.
  - CI запускает тесты на тех же версиях.
  - Финальное smoke-тестирование обязательно на staging VPS.
- Документация деплоя описывает обе вселенные.
- Любой переход на Docker prod — отдельный ADR.

## Альтернативы

- **Docker всюду (prod через Docker).** Дополнительная сложность, доп. оркестрация (Compose / Swarm / k8s), не оправдано на текущем масштабе.
- **Native всюду (без Docker даже локально).** Тяжёлый онбординг, риски конфликтов.
- **Vagrant** — устарел, медленнее, не используется в команде.

## Когда пересмотреть

- Появится Kubernetes-ready инфраструктура и команда DevOps.
- Несколько серверов, нужна оркестрация.
- Production требует часто менять окружение.

## Связанные документы

- [32-docker-architecture](../32-docker-architecture.md)
- [33-local-development](../33-local-development.md)
- [adr/0009-vps-deployment-strategy](0009-vps-deployment-strategy.md)
