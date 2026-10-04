# 49. Staging на Beget: dev.zaborprofil.ru

Автодеплой стенда `https://dev.zaborprofil.ru` на хостинг Beget: push в ветку `dev` -> GitHub Actions -> SSH -> Beget.
Схема повторяет проверенный подход из проекта `m2saratov` (деплой по SSH скриптом из stdin, отдельный git-клон на сервере, Basic Auth в приложении, noindex, автооткат), но адаптирована под Symfony 8: Doctrine migrations (MySQL 8.4) вместо `install.php`, prebuilt frontend из CI вместо `sync-assets`, web root `public_html/`.

> Стенд работает без Docker (native stack на сервере). Production этим процессом **не** деплоится.
> Для production по-прежнему действует release-схема из [34-deployment](34-deployment.md) и [35-cicd](35-cicd.md).

> Старый workflow `.github/workflows/deploy.yml` (деплой на VPS по push в `main`) отключён по умолчанию и не удалён. Включить автозапуск обратно: задать repository variable `VPS_DEPLOY_ENABLED=true` (Settings -> Secrets and variables -> Actions -> Variables). Ручной запуск `workflow_dispatch` работает всегда. Подробности: [35-cicd](35-cicd.md).

## Что входит

| Файл | Назначение |
|---|---|
| `.github/workflows/deploy-staging-beget.yml` | workflow «Deploy staging (Beget)»: сборка frontend, SSH-деплой, smoke-check |
| `tools/deploy/deploy-beget.sh` | скрипт деплоя; передаётся на сервер через stdin, на сервере его хранить не нужно |
| `.env.staging.beget.example` | шаблон `~/dev.zaborprofil.ru/.env.local` на сервере |
| `public_html/.htaccess` | front controller для Apache, проброс `Authorization`, запрет исполнения PHP в `uploads/` |
| `src/Shared/Infrastructure/Http/StagingAccessSubscriber.php` | Basic Auth и `X-Robots-Tag: noindex, nofollow` при `APP_ENV=staging` |
| `tests/shell/deploy-beget.sh` | тест логики деплоя на временных git-репозиториях (запускается в CI) |
| `tests/Unit/Shared/Infrastructure/Http/StagingAccessSubscriberTest.php` | unit-тест Basic Auth |

## Как это работает

```text
git push origin dev
   └─ workflow "Deploy staging (Beget)"
        ├─ php -l по всем *.php, bash -n deploy-beget.sh   (быстрые проверки, полный CI не ждём)
        ├─ npm ci && npm run build  -> frontend-build.tar.gz (build/ + BUILD_COMMIT)
        ├─ ssh: cat > ~/.deploy-tmp/frontend-build-<sha>.tar.gz
        └─ ssh: bash -s -- <sha> < tools/deploy/deploy-beget.sh      (в ~/dev.zaborprofil.ru)
             ├─ preflight (APP_ENV=staging, отдельная БД, Basic Auth настроен)
             ├─ var/cache и var/sessions -> симлинки в shared/ (сессии переживают деплой)
             ├─ git checkout --force -B staging-deployed <sha>, composer install --no-dev
             ├─ установка prebuilt frontend (проверка BUILD_COMMIT == sha)
             ├─ doctrine:migrations:migrate, очистка shared/cache, cache:warmup, app:smoke:test
             └─ при ошибке: автооткат кода и frontend на предыдущий коммит
        └─ smoke-check: без пароля 401, с паролем /health/live = 200 + noindex, /admin/login = 200;
           затем прогрев публичных страниц (/, /sitemap.xml, /robots.txt)
```

Ключевые решения:

- **Node.js на сервере не нужен.** Frontend собирается в GitHub Actions (Node 25.9.0) и приезжает архивом. Скрипт сверяет `BUILD_COMMIT` из архива с деплоимым коммитом: assets и шаблоны не разойдутся.
- **Один стенд, одна очередь.** `concurrency: deploy-staging-beget` с `cancel-in-progress: false`: если пришло несколько пушей, выполняется только последний ожидающий.
- **Staging-клон отдельный** (`~/dev.zaborprofil.ru`), локальная ветка `staging-deployed`, `checkout --force` — любая ветка или SHA, локальные правки отслеживаемых файлов затираются. Игнорируемые файлы (`.env.local`, `vendor/`, `var/`, `public_html/uploads/`, `public_html/build/`) не затрагиваются.
- **Миграции на staging всегда применяются автоматически.** Изменения БД при откате кода не откатываются (только восстановление из бэкапа).
- **Basic Auth в приложении, а не в `.htaccess`.** На Beget директивы `AuthType/AuthUserFile/Require` в `.htaccess` ломают PHP (500, PHP работает как CGI). Заголовок `Authorization` пробрасывается правилом `RewriteRule .* - [E=HTTP_AUTHORIZATION:...]`.
- **Закрыты только PHP-страницы.** Статику, которую Apache/nginx отдаёт мимо PHP (`/build/*`, `/uploads/*`), пароль не закрывает.
- **Кэш и сессии в `shared/`.** Каталог `~/dev.zaborprofil.ru/shared/` (в `.gitignore`, вне `public_html`) содержит `cache/` и `sessions/`; `var/cache` и `var/sessions` — симлинки на них (`DEPLOY_SHARED_DIR` меняет расположение). Сессии не теряются при деплое; `cache/` (скомпилированный контейнер, Twig и файловый кэш приложения) очищается на каждом деплое и сразу прогревается `cache:warmup`. Симлинки создаются до `composer install`, поэтому первый запуск ничего не требует вручную. Старое содержимое реального `var/sessions` при первом деплое переносится в `shared/sessions`.
- **Production не затронут.** Preflight отказывается работать, если `APP_ENV` не `staging`, если каталог совпадает с `DEPLOY_PROD_PATH` (в том числе через симлинк) или БД совпадает с прод-БД.

## Предварительные условия (проверить до первого деплоя)

Стек проекта: PHP `>=8.5`, MySQL `>=8.4` (InnoDB, utf8mb4). Redis **не используется**: кэш приложения файловый (`cache.adapter.filesystem`, см. [23-cache](23-cache.md) и [ADR-0007](adr/0007-filesystem-cache-and-doctrine-messenger.md)), очередь Messenger — Doctrine transport. На Beget нужно убедиться, что доступно:

1. PHP 8.5 CLI и web с расширениями `ctype`, `iconv`, `intl`, `mbstring`, `pdo_mysql`, `xml` (скрипт проверяет их сам и останавливается с понятной ошибкой).
2. MySQL версии 8.4 или совместимой (версия задаётся в `serverVersion=8.4` в `DATABASE_URL`). Права пользователя БД и глобальные переменные (`innodb_*`, `lock_wait_timeout`) на хостинге могут отличаться от Docker, см. [18-migrations](18-migrations.md).
3. SSH-доступ и возможность выбрать версию PHP для сайта в панели.

## Настройка сервера (один раз)

### 1. Панель Beget (делает владелец аккаунта)

1. «Сайты»: у `dev.zaborprofil.ru` свой сайт с каталогом `dev.zaborprofil.ru`, document root `~/dev.zaborprofil.ru/public_html`. Поддомен не привязывать к сайту основного домена.
2. Версия PHP сайта: 8.5 (как в `composer.json`).
3. SSL (Let's Encrypt) для `dev.zaborprofil.ru` включить обязательно, иначе при HSTS с `includeSubDomains` на основном домене браузер не откроет поддомен.
4. DNS: `dev.zaborprofil.ru` указывает на сервер Beget (A-запись или делегирование на DNS Beget).
5. «MySQL»: создать отдельную БД и пользователя для staging (не использовать боевую), кодировка `utf8mb4`. На Beget имя БД обычно совпадает с именем пользователя, хост — `localhost`.
6. Если включено ограничение SSH по IP, снять его: адреса GitHub Actions меняются.

### 2. Клон репозитория в существующей папке сайта

Папку сайта Beget нельзя пересоздать, поэтому `git init` на месте:

```bash
cd ~/dev.zaborprofil.ru
rm -f public_html/index.html                                  # заглушка Beget
git config --global --add safe.directory "$(pwd -P)"          # если git ругается на dubious ownership
git init -q
git remote add origin git@github.com:ariestop/zaborprofil.git # или https-адрес; доступ к GitHub должен работать без пароля
git config core.fileMode false                                # права файлов на Beget отличаются от git
git fetch origin
git checkout -f -B staging-deployed origin/dev
```

Для приватного репозитория на сервере нужен deploy key только на чтение (добавляет владелец репозитория). Проверка: `git fetch origin` проходит без запросов пароля.

### 3. PHP и Composer по абсолютным путям

В SSH-сессии Beget по умолчанию старый системный PHP и Composer 1. Скрипт использует явные пути, а не алиасы `.bashrc` (в неинтерактивном SSH они не работают):

```bash
ls /usr/local/bin/php*                                        # найти php8.5
cd ~ && /usr/local/bin/php8.5 -r "copy('https://getcomposer.org/installer','composer-setup.php');" \
  && /usr/local/bin/php8.5 composer-setup.php --filename=composer.phar && rm composer-setup.php
/usr/local/bin/php8.5 ~/composer.phar --version
```

Значения по умолчанию в скрипте: `PHP_BIN=/usr/local/bin/php8.5`, `COMPOSER_PHAR=~/composer.phar`. Переопределяются переменными окружения.

### 4. `.env.local` staging

Скопировать `.env.staging.beget.example` в `~/dev.zaborprofil.ru/.env.local`, заполнить значения, `chmod 600`. Хеш пароля Basic Auth (пароль придумывает владелец; в общий лог и чаты не писать):

```bash
cd ~/dev.zaborprofil.ru
sed -i '/^STAGING_AUTH_HASH=/d' .env.local
/usr/local/bin/php8.5 -r 'echo "STAGING_AUTH_HASH=\x27".password_hash($argv[1], PASSWORD_BCRYPT)."\x27\n";' 'ПАРОЛЬ' >> .env.local
chmod 600 .env.local
```

Правила:

- Хеш bcrypt обязательно в **одинарных кавычках**: Symfony Dotenv раскрывает `$...` в значениях без кавычек и портит хеш. Скрипт проверяет формат и отказывается деплоить.
- `APP_ENV=staging`, `STAGING_AUTH_ENABLED=1`, `MAILER_DSN=null://null` (письма со стенда не уходят). `REDIS_URL` не нужен.
- `DATABASE_URL`: только `mysql://...?serverVersion=8.4&charset=utf8mb4`, спецсимволы пароля кодируются (`rawurlencode`). База должна отличаться от боевой.
- `APP_SECRET`: случайная строка (`openssl rand -hex 32`), не шаблонное значение.
- Смена пароля: повторить блок с хешем, деплой не нужен.

### 5. Данные staging-БД

Структура создаётся миграциями Doctrine при первом деплое. Данные для разработки — через fixtures/seed ([47-dev-database-state](47-dev-database-state.md)). Дампы продакшена в Git не кладутся; если копия прода нужна на стенде, её делает владелец вручную, учитывая, что в ней есть персональные данные (стенд закрыт паролем и без почты).

### 5a. Cron планировщика публикаций

Отложенная публикация и снятие страниц выполняются командой `app:content:publish-scheduled`. В панели Beget -> «Cron» добавить задачу «каждую минуту»
(`* * * * *`): `cd ~/dev.zaborprofil.ru && /usr/local/bin/php8.5 bin/console app:content:publish-scheduled --env=staging --no-interaction >> var/log/publish-scheduled.log 2>&1`.
Подробности, `--dry-run` и мониторинг: [50-publishing-workflow](50-publishing-workflow.md).

### 6. SSH-ключ деплоя

Отдельный ключ без пароля только для деплоя (на компьютере владельца, не на сервере):

```bash
ssh-keygen -t ed25519 -N '' -C 'github-actions-deploy-zaborprofil' -f ~/.ssh/zaborprofil_deploy
ssh-copy-id -i ~/.ssh/zaborprofil_deploy.pub <BEGET_USER>@<SSH_HOST>
ssh -i ~/.ssh/zaborprofil_deploy -o IdentitiesOnly=yes <BEGET_USER>@<SSH_HOST> 'cd ~/dev.zaborprofil.ru && git log --oneline -1'
ssh-keyscan -H <SSH_HOST>                                      # вывод -> секрет DEPLOY_KNOWN_HOSTS
```

Приватный ключ уходит только в секрет GitHub, публичный — только на сервер. После загрузки в секрет локальную копию приватного ключа можно удалить.

## Секреты и переменные GitHub Actions

Settings -> Secrets and variables -> Actions (или секреты окружения `staging`):

| Имя | Тип | Обязателен | Описание |
|---|---|---|---|
| `DEPLOY_HOST` | secret | да | SSH-хост Beget (например `<логин>.beget.tech`) |
| `DEPLOY_USER` | secret | да | SSH-пользователь Beget |
| `DEPLOY_SSH_KEY` | secret | да | приватный ключ целиком, со строками `-----BEGIN` и `-----END` |
| `DEPLOY_KNOWN_HOSTS` | secret | желательно | вывод `ssh-keyscan -H <SSH_HOST>`; без него ключ хоста берётся на лету без проверки |
| `DEPLOY_PORT` | secret | нет | порт SSH, по умолчанию `22` |
| `STAGING_BASIC_AUTH` | secret | желательно | `логин:пароль` Basic Auth для smoke-check с паролем |
| `STAGING_PATH` | secret или variable | нет | каталог staging, по умолчанию `~/dev.zaborprofil.ru` |
| `STAGING_URL` | variable | нет | URL smoke-check, по умолчанию `https://dev.zaborprofil.ru/` |

Пока `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY` не заданы, workflow завершается предупреждением и ничего не делает: это штатное поведение, а не ошибка.

Для облачных агентов Cursor, которые открывают стенд в браузере, секрет `STAGING_BASIC_AUTH=логин:пароль` добавляется в Cursor Dashboard -> Cloud Agents -> Secrets (URL входа `https://логин:пароль@dev.zaborprofil.ru/`; такие URL не печатать в общий вывод).

GitHub Environment `staging`: Required reviewers не включать, иначе пропадает смысл быстрых итераций.

## Рабочий процесс

1. Изменения попадают в ветку `dev` (через PR или прямым push), workflow запускается автоматически.
2. Результат виден через несколько минут на `https://dev.zaborprofil.ru/`.
3. Следить за деплоем: `gh run list --workflow=deploy-staging-beget.yml -L 3`, `gh run watch <id>`.
4. Ручной запуск любой ветки/SHA: Actions -> «Deploy staging (Beget)» -> Run workflow -> `ref`.
5. Логи на сервере: `~/dev.zaborprofil.ru/var/log/deploy.log`, история — `var/log/deploy-history.log`.

Важно: push в `dev` запускает workflow, только если файл `.github/workflows/deploy-staging-beget.yml` уже есть в коммите, на который указывает `dev`. Поэтому workflow сначала должен попасть в `dev` (например, merge `main` в `dev` после принятия PR с этим деплоем). Ветки `main` и `dev` исторически разошлись, конфликты разрешает владелец ветки.

Правила стенда: стенд один, его одновременно занимает одна работа; `git push --force` в `dev` затирает чужие коммиты, поэтому для обычной работы использовать PR/merge, а не force-push.

## Первый деплой вручную (пока workflow не в `dev`)

Без архива frontend `app:smoke:test` не найдёт `public_html/build/.vite/manifest.json` и деплой откатится, поэтому архив собирается локально (`make npm-build`) из того же коммита, который деплоится:

```bash
SHA="$(git rev-parse HEAD)"                       # коммит должен быть запушен в GitHub
make npm-build
mkdir -p dist && cp -r public_html/build dist/build && printf '%s\n' "$SHA" > dist/BUILD_COMMIT
tar -czf frontend-build.tar.gz -C dist .
ssh <BEGET_USER>@<SSH_HOST> 'mkdir -p "$HOME/.deploy-tmp" && cat > "$HOME/.deploy-tmp/frontend-build.tar.gz"' < frontend-build.tar.gz
ssh <BEGET_USER>@<SSH_HOST> "DEPLOY_PATH='~/dev.zaborprofil.ru' FRONTEND_BUILD_ARCHIVE='~/.deploy-tmp/frontend-build.tar.gz' bash -s -- $SHA" < tools/deploy/deploy-beget.sh
curl -sI https://dev.zaborprofil.ru/ | head -1                      # 401
curl -sI -u 'dev:ПАРОЛЬ' https://dev.zaborprofil.ru/health/live     # 200 и X-Robots-Tag: noindex
```

Пароль в команды, остающиеся в общей истории, не вставлять.

## Автооткат

Если после смены кода любой шаг упал (composer, frontend, миграции, smoke-тест), скрипт:

1. возвращает код на предыдущий коммит (`checkout --force`);
2. возвращает предыдущий `public_html/build`;
3. выполняет `composer install --no-dev` и очищает `shared/cache` (сессии остаются).

Изменения БД, если миграции успели примениться, не откатываются (DDL в MySQL не транзакционен, неудавшаяся миграция может оставить схему в промежуточном состоянии): откат БД только из бэкапа. Если автооткат не удался, в логе будет строка `КРИТИЧНО: ...`.

## Чек-лист проверки после настройки

1. SSH по ключу без пароля: `ssh -i <ключ> -o IdentitiesOnly=yes <user>@<host> 'cd ~/dev.zaborprofil.ru && git log --oneline -1'`.
2. `/usr/local/bin/php8.5 -v` — PHP 8.5, `php8.5 -m | grep pdo_mysql`; `php8.5 ~/composer.phar --version` — Composer 2; `git fetch origin` без пароля.
3. `.env.local` содержит `APP_ENV=staging`, `mysql://` `DATABASE_URL` с отдельной БД, `STAGING_AUTH_ENABLED=1`, хеш в одинарных кавычках.
4. В GitHub заданы `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY` (желательно `DEPLOY_KNOWN_HOSTS`, `STAGING_BASIC_AUTH`).
5. `curl -sI https://dev.zaborprofil.ru/` -> `401` и `WWW-Authenticate: Basic`; с верным паролем `/health/live` -> `200` и `X-Robots-Tag: noindex, nofollow`.
6. `https://dev.zaborprofil.ru/robots.txt` (с паролем) содержит `Disallow: /` (для окружения не `prod` это поведение приложения).
7. Пуш в `dev` запускает workflow, все шаги зелёные, в `deploy.log` есть `=== staging завершён: <sha> ===`.
8. Повторный деплой того же коммита проходит; деплой другой ветки через Run workflow переключает код.
9. Негативная проверка: запуск скрипта в каталоге прода или с `APP_ENV` не `staging` падает на preflight (покрыто `tests/shell/deploy-beget.sh`).

## Типичные ошибки

| Симптом | Причина | Решение |
|---|---|---|
| 500 на любом `.php` после добавления Basic Auth | `Auth*`-директивы в `.htaccess` ломают PHP на Beget (CGI) | убрать их; пароль проверяет приложение |
| Пароль не принимается, хотя верный | PHP как CGI не получает `Authorization` | проверить правило `E=HTTP_AUTHORIZATION` в `public_html/.htaccess` до `index.php` |
| Поддомен не открывается по http | на основном домене HSTS с `includeSubDomains`, на поддомене нет SSL | включить Let's Encrypt для `dev.zaborprofil.ru` |
| `STAGING_AUTH_HASH должен быть bcrypt-хешем в ОДИНАРНЫХ кавычках` | Dotenv раскрывает `$...` | взять хеш в `'...'`, сгенерировать заново |
| `не PHP 8.5+` / `Не найден PHP` | неверный путь или версия | `ls /usr/local/bin/php*`, задать `PHP_BIN` |
| `В ... нет расширений: pdo_mysql ...` | расширение не включено для PHP 8.5 | включить в панели Beget |
| `DATABASE_URL должен начинаться с mysql://` | в `.env.local` остался старый pgsql-URL или значение из Docker | взять формат из `.env.staging.beget.example` |
| `fatal: detected dubious ownership` | папка сайта принадлежит другому пользователю | `git config --global --add safe.directory "$(pwd -P)"` |
| `git status` показывает все файлы изменёнными | права файлов на Beget отличаются от git | `git config core.fileMode false` |
| Все страницы 500, `.env.local` не найден | нет `.env.local` или он не читается | создать по шаблону, `chmod 600` |
| `Staging использует ту же БД, что и прод` | совпали хост и имя БД | создать отдельную БД |
| `Другой деплой уже выполняется` | параллельный запуск (`flock` на `var/deploy.lock`) | дождаться; при fallback на `mkdir` удалить `var/deploy.lock.d` |
| `Frontend собран из коммита ..., а деплоится ...` | архив собран из другого коммита | перезапустить workflow (архив собирается в том же запуске) |
| Push в `dev` не запускает деплой | в коммите нет `deploy-staging-beget.yml` | влить workflow в `dev`; пока что запускать вручную |
| Workflow зелёный, но шаги пропущены | не заданы `DEPLOY_HOST`/`DEPLOY_USER`/`DEPLOY_SSH_KEY` | добавить секреты |
| GitHub не подключается по SSH | в панели включено ограничение по IP | снять ограничение |
| `/health/ready` отвечает 503 | недоступна БД или каталог кэша | проверить `DATABASE_URL`, права на `shared/cache` и `shared/sessions` |
| Пользователей «разлогинило» после деплоя | `var/sessions` не был симлинком на `shared/sessions` | деплой сам создаёт симлинк; проверить `ls -l var/` |

## Проверки перед изменением деплоя

```bash
bash -n tools/deploy/deploy-beget.sh
shellcheck -x tools/deploy/deploy-beget.sh tests/shell/deploy-beget.sh
bash tests/shell/deploy-beget.sh
vendor/bin/phpunit tests/Unit/Shared/Infrastructure/Http
```

## Связанные документы

- [34-deployment](34-deployment.md)
- [35-cicd](35-cicd.md)
- [27-config-and-env](27-config-and-env.md)
- [29-healthchecks](29-healthchecks.md)
- [36-backup-restore](36-backup-restore.md)
