# 47. Dev database state

## Цель

Новый ПК разработки должен получать одинаковую структуру БД и базовые dev-данные после клонирования репозитория, без передачи Docker volumes и реальных backup-файлов.

Канонический путь:

```bash
make init
# или для уже поднятого окружения
make reset-db
```

`make reset-db` удаляет локальную БД, создаёт её заново, применяет Doctrine migrations и запускает `make fixtures`. Если `doctrine/doctrine-fixtures-bundle` ещё не подключён, fixture-шаг является no-op и выводит информационное сообщение.

## Что хранится в Git

- Doctrine migrations.
- Код fixtures, seeders, builders и маленькие reference datasets.
- Документация по локальному запуску и restore.
- При необходимости — маленький обезличенный dev snapshot/seed, который прошёл ручную проверку.

## Что не хранится в Git

- Docker images.
- Docker volumes: `postgres_data`, `redis_data`, `uploads_data`.
- Production/staging dumps и per-deploy backups.
- Реальные uploads, кроме технических placeholders вроде `.gitkeep`.
- `.env.local`, пароли, токены, приватные ключи.
- Персональные данные клиентов, лидов, администраторов, email, телефоны, IP, user-agent и audit trail с реальными значениями.

## Как будет идти “бэкап БД в Git”

Это не должен быть настоящий backup. Git используется только для воспроизводимого dev seed.

Процесс:

1. Разработчик готовит dev-данные локально.
2. Данные обезличиваются: реальные контакты, токены, IP, email, телефоны и бизнес-чувствительные тексты заменяются тестовыми значениями.
3. Предпочтительно эти данные оформляются как fixtures в коде.
4. Если fixtures недостаточно, создаётся маленький SQL/JSON/YAML snapshot только для dev.
5. Snapshot проверяется вручную перед commit: размер, отсутствие секретов, отсутствие персональных данных.
6. Другой разработчик получает состояние через `git pull` и `make reset-db`.

Если snapshot становится большим, часто меняется или содержит рискованные данные, его нельзя хранить в Git. В таком случае нужен внешний зашифрованный storage и отдельный restore-runbook.

## Рекомендуемый формат

Предпочтительный вариант — fixtures:

```bash
make reset-db
```

Целевой fixture-набор должен создавать:

- администратора с тестовыми credentials из документации или `.env.local.example`;
- базовые страницы и меню;
- SEO redirects / sitemap examples;
- media placeholders без реальных файлов клиентов;
- тестовые leads только с fake-данными.

Текущее baseline-состояние после миграций включает dev-страницу главной (`path=/`)
с блоком `slider`, чтобы публичный фронт сразу демонстрировал работу блочного SSR
и интерактивного Swiper-слайдера.

SQL snapshot допустим только как временная мера для небольшого demo-набора. Он должен быть текстовым или сжатым только при реальной необходимости; перед commit обязательно проверять содержимое.

## Связанные документы

- [33-local-development](33-local-development.md)
- [32-docker-architecture](32-docker-architecture.md)
- [36-backup-restore](36-backup-restore.md)
- [31-testing-strategy](31-testing-strategy.md)
