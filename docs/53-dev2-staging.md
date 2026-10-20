# Отдельный стенд dev2.zaborprofil.ru

Ветка `dev2` публикуется отдельным workflow **Deploy dev2 (Beget)**.
Push в `dev2` запускает сборку и деплой; ручной запуск допустим только из этой ветки.
Основной workflow для `dev.zaborprofil.ru` не изменён.

## Перед первым запуском

Создания DNS/поддомена недостаточно. На Beget должны быть подготовлены:

1. Отдельный сайт с web root `~/dev2.zaborprofil.ru/public_html`, PHP 8.5 и HTTPS.
2. Отдельный каталог `~/dev2.zaborprofil.ru`. Первый деплой создаёт в нём git-клон,
   если там только стартовые файлы хостинга. Эти файлы сохраняются в `.dev2-bootstrap-backup`.
3. Отдельная MySQL 8.4 БД с уникальным именем. Для сравнения дизайнов нужна копия
   контента dev и копия его uploads; не подключайте dev2 к живой БД dev.
   Дампы и реальные uploads в Git не добавляются.
4. Собственный `.env.local` по `.env.staging.beget.example`:
   `APP_ENV=staging`, отдельный `DATABASE_URL`, собственный `APP_SECRET`,
   `SITE_URL=https://dev2.zaborprofil.ru` и `DEFAULT_URI=https://dev2.zaborprofil.ru`.
   Сохранить защиту staging: Basic Auth или уже предусмотренный явно открытый режим.
   Уведомления отключить: `MAILER_DSN=null://null`, не подключать реальные Telegram-уведомления.
5. Кэш, сессии и uploads принадлежат dev2. Не использовать симлинки на каталоги dev.

Первичная настройка git/PHP/Composer описана в [49-beget-staging-deploy.md](49-beget-staging-deploy.md);
для нового стенда заменяются каталог, адрес и реквизиты БД.
Workflow не создаёт БД и не переносит контент автоматически. При отсутствии `.env.local`
первичный bootstrap создаёт его из `DEV2_DATABASE_URL` и Basic Auth, с правами 600.
Существующая конфигурация не перезаписывается. Секреты передаются по SSH stdin без вывода в логи.

## GitHub Actions

Используется существующее environment `staging` для доступа к SSH-секретам Beget
(`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PORT`, `DEPLOY_KNOWN_HOSTS`).
Это общие реквизиты сервера, а не общий каталог или БД.

- `DEV2_STAGING_PATH` — secret или variable, по умолчанию `~/dev2.zaborprofil.ru`.
- `DEV2_DATABASE_URL` — secret с URL отдельной MySQL БД для первичной настройки.
- `DEV2_STAGING_BASIC_AUTH` — secret `логин:пароль`; при отсутствии используется
  существующий `STAGING_BASIC_AUTH`. Это пароль стенда, не пароль админки.
  Basic Auth включён при bootstrap, исходящие уведомления отключены.
- `STAGING_PATH` — существующий путь dev, используется только для проверки разделения БД.
- URL фиксирован: `https://dev2.zaborprofil.ru/`; `STAGING_URL` его не переопределяет.
- Отдельная очередь `deploy-dev2-beget` и отдельные имена временных архивов.

После безопасной первичной настройки, перед загрузкой сборки проверка только чтением подтверждает git-клон, разные каталоги,
staging-конфигурацию, правильный URL, разные имена БД и отсутствие ссылок на внешние
cache/sessions/uploads. Если сервер ещё не подготовлен, workflow завершится ошибкой
с объяснением, не меняя код dev или production.

Далее работает существующий `deploy-beget.sh`: сборка с совпадающим SHA,
Composer, миграции отдельной БД dev2, прогрев кэша и smoke-check.
Код и frontend автоматически откатываются при ошибке; миграции БД не откатываются.
