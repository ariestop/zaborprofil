# 37. Runbooks

> Операционный runbook для production-системы Symfony CMS Engine (`zaborprofil.ru`).
> Документ предназначен для реального использования во время инцидента — ночью, под давлением, без времени на размышления.

См. также:

- [27-config-and-env](27-config-and-env.md)
- [28-logging-observability](28-logging-observability.md)
- [29-healthchecks](29-healthchecks.md)
- [30-error-handling](30-error-handling.md)
- [32-docker-architecture](32-docker-architecture.md)
- [34-deployment](34-deployment.md)
- [35-cicd](35-cicd.md)
- [36-backup-restore](36-backup-restore.md)
- [39-agent-guide](39-agent-guide.md)
- [40-cursor-rules](40-cursor-rules.md)
- [44-troubleshooting](44-troubleshooting.md)

---

## 1. Назначение документа

### Для кого

- Backend-разработчики, дежурные по сайту.
- DevOps / SRE / администратор VPS.
- AI-агенты в Cursor, выполняющие операционные задачи.

### Когда использовать

- Сайт упал / отдаёт 5xx / отдаёт неверные статусы.
- Обнаружена аномалия после deploy.
- Алерт от мониторинга (healthcheck, диск, память, очередь).
- При плановых работах с риском (миграции, восстановление, переключение релизов).

### Чем runbook отличается от troubleshooting

- [44-troubleshooting](44-troubleshooting.md) — ошибки **разработки**: «у меня в dev не запускается / не собирается / ругается линтер».
- **Runbook (этот документ)** — **production-инциденты**: «сайт лежит / упала база / очередь не идёт / 502».

### Что делать в первую очередь

1. Не паниковать. Не запускать никаких изменений до диагностики.
2. Открыть «**Раздел 7. Emergency checklist**» и пройти его сверху вниз.
3. Только после диагностики переходить к нужному инциденту в разделе «**6. Runbooks по инцидентам**».
4. Если есть риск потери данных — стоп, см. «**Раздел 9. Escalation**».

---

## 2. Важные правила перед началом работ

Эти правила нарушать **нельзя**. Они спасают данные и стабильность.

- Не удалять данные (`rm -rf`, `DROP`, `TRUNCATE`, `dropdb`) без свежего backup и подтверждения, что backup читается (`pg_restore --list`).
- Не запускать миграции вслепую. Сначала — `php bin/console doctrine:migrations:status`, `doctrine:migrations:list`, проверка SQL миграции в файле `migrations/`.
- Не делать `composer update` на production. Только `composer install --no-dev --optimize-autoloader`.
- Не менять `APP_ENV` на `dev` в production.
- Не включать `APP_DEBUG=true` в production. Это раскрывает stacktrace, конфиги и переменные окружения посетителям сайта.
- Сначала смотреть логи (раздел 5), потом перезапускать сервисы. Перезапуск без понимания причины маскирует проблему.
- Перед изменениями фиксировать состояние: `git rev-parse HEAD`, `php bin/console doctrine:migrations:status`, `systemctl status ...`, `df -h`, `free -m`, `redis-cli info`.
- При серьёзной аварии — **снять snapshot**: `pg_dump`, `tar` для `public_html/uploads/`, копия `shared/.env.local`, копия `var/log/`. См. [36-backup-restore](36-backup-restore.md).
- Все деструктивные команды (отмеченные **ВНИМАНИЕ**) выполнять только после двойной проверки путей и параметров.
- Любое изменение фиксировать в incident report (раздел 9 ниже / шаблон в конце документа).

---

## 3. Базовые пути и команды проекта

### Placeholder’ы

В runbook используются placeholder’ы:

- `<project>` — имя проекта в FS (например, `zaborprofil`).
- `<domain>` — публичный домен (например, `zaborprofil.ru`).
- `<db_name>` — имя БД (например, `zaborprofil`).
- `<user>` — Linux user / DB user (часто `deploy`, `www-data`, `zaborprofil`).
- `<release_dir>` — путь к конкретному релизу (например, `/var/www/<project>/releases/20260502-090000`).

Production layout (см. [34-deployment](34-deployment.md)):

```text
/var/www/<project>/
├── releases/<timestamp>/   # каждый релиз — отдельная папка
├── shared/
│   ├── .env.local
│   ├── public_html/uploads/
│   ├── var/log/
│   └── backups/
└── current -> releases/<timestamp>
```

`current` — symlink на текущий активный релиз.

### Базовый вход

```bash
ssh <user>@<host>
cd /var/www/<project>/current
pwd
whoami
hostname
date
uptime
```

### Базовые console-команды

```bash
php bin/console about
php bin/console debug:router
php bin/console debug:container
php bin/console debug:autowiring
php bin/console doctrine:migrations:status
php bin/console doctrine:query:sql "SELECT 1"
php bin/console messenger:stats
php bin/console messenger:failed:show
php bin/console app:smoke:test
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
```

### Composer / npm на сервере

```bash
composer validate --strict
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

> На production **запрещено** `composer update` и `npm install` без lockfile.

---

## 4. Карта сервисов production

| Сервис | Назначение | Имя unit |
|---|---|---|
| `nginx` | TLS termination, статика, FastCGI к PHP | `nginx` |
| `php-fpm` | PHP worker для Symfony | `php8.5-fpm` (или `php-fpm`) |
| `postgresql` | Основная БД | `postgresql` |
| `redis-server` | Кэш Symfony, опц. сессии | `redis-server` |
| Messenger worker | Очереди (Doctrine transport) | `<project>-messenger` |
| `cron` / systemd timers | Backups, sitemap, cleanup, certbot | `cron` / `*.timer` |
| `certbot` | Автообновление TLS (через timer) | `certbot.timer` |

### Базовые команды по сервисам

```bash
systemctl status nginx --no-pager
systemctl status php8.5-fpm --no-pager
systemctl status php-fpm --no-pager           # альтернативное имя
systemctl status postgresql --no-pager
systemctl status redis-server --no-pager
systemctl status redis --no-pager             # альтернативное имя
systemctl status <project>-messenger --no-pager
systemctl status certbot.timer --no-pager

systemctl restart nginx
systemctl reload nginx
systemctl restart php8.5-fpm
systemctl reload php8.5-fpm
systemctl restart postgresql
systemctl restart redis-server
systemctl restart <project>-messenger

journalctl -u nginx -n 100 --no-pager
journalctl -u php8.5-fpm -n 100 --no-pager
journalctl -u postgresql -n 100 --no-pager
journalctl -u redis-server -n 100 --no-pager
journalctl -u <project>-messenger -n 200 --no-pager
journalctl -xe --no-pager
```

Если имя php-fpm unit неизвестно:

```bash
systemctl list-units --type=service | grep -Ei 'php|fpm'
ls /etc/systemd/system/ /lib/systemd/system/ | grep -Ei 'php|fpm'
```

---

## 5. Карта логов

### Логи приложения (Symfony)

```bash
tail -n 100 /var/www/<project>/current/var/log/prod.log
tail -f    /var/www/<project>/current/var/log/prod.log
grep -E 'ERROR|CRITICAL|ALERT|EMERGENCY' /var/www/<project>/current/var/log/prod.log | tail -n 100
```

> `var/log/` симлинкуется на `shared/var/log/`. Реальные файлы — `/var/www/<project>/shared/var/log/`.

### Логи nginx

```bash
tail -n 100 /var/log/nginx/access.log
tail -n 100 /var/log/nginx/error.log
tail -f    /var/log/nginx/error.log
grep -E ' 5[0-9]{2} ' /var/log/nginx/access.log | tail -n 100
```

### Логи PHP-FPM

```bash
tail -n 100 /var/log/php8.5-fpm.log
tail -n 100 /var/log/php-fpm/error.log
journalctl -u php8.5-fpm -n 200 --no-pager
```

### Логи PostgreSQL

```bash
ls /var/log/postgresql/
tail -n 200 /var/log/postgresql/postgresql-18-main.log
journalctl -u postgresql -n 200 --no-pager
```

### Логи Redis

```bash
ls /var/log/redis/
tail -n 200 /var/log/redis/redis-server.log
journalctl -u redis-server -n 200 --no-pager
```

### Системные логи

```bash
journalctl -xe --no-pager
journalctl -p err -n 200 --no-pager
tail -n 200 /var/log/syslog       # Debian/Ubuntu
tail -n 200 /var/log/messages     # RHEL/CentOS
dmesg | tail -n 200
```

### Telegram-канал критических ошибок

При наличии настроенного `TelegramErrorHandler` (см. [28-logging-observability](28-logging-observability.md)) — посмотреть последние сообщения в канале алертов перед началом диагностики.

---

## 6. Runbooks по инцидентам

Структура каждого пункта одинакова:
**Симптомы → Возможные причины → Быстрая диагностика → Пошаговое решение → Проверка → Профилактика.**

---

### 1. Сайт полностью недоступен

#### Симптомы

- Браузер показывает «Не удалось установить соединение», `ERR_CONNECTION_REFUSED`, `ERR_CONNECTION_TIMED_OUT`.
- `curl -I https://<domain>` зависает или возвращает ошибку.
- Мониторинг показывает downtime по всем endpoints.

#### Возможные причины

1. VPS выключен / перезагружается / провайдер ушёл в обслуживание.
2. Сетевые проблемы у провайдера / firewall.
3. Упал nginx.
4. DNS указывает не на тот IP.
5. Истёк / неверно установлен TLS-сертификат (для HTTPS).
6. SSH доступен, но 80/443 не слушаются.

#### Быстрая диагностика

```bash
ping <domain>
dig +short <domain>
dig +short <domain> AAAA
curl -I --max-time 10 http://<domain>
curl -I --max-time 10 https://<domain>
ssh <user>@<host>
```

На сервере:

```bash
hostname
date
uptime
systemctl status nginx --no-pager
ss -ltnp | grep -E ':80|:443'
ufw status
iptables -L -n | head -n 50
```

#### Пошаговое решение

1. Проверить, что VPS жив: `ping`, SSH.
2. Если VPS не пингуется — открыть панель провайдера, проверить статус ноды; если KVM/console доступна — войти и посмотреть `journalctl -xe`.
3. Если SSH работает, но 80/443 закрыты:
   - `systemctl status nginx --no-pager`;
   - если `inactive`/`failed` — переходить к **«2. Nginx не отвечает»**;
   - если `active (running)` — проверить `ss -ltnp | grep -E ':80|:443'`. Если порты не слушаются — `nginx -t`, посмотреть `/etc/nginx/nginx.conf`, `nginx -s reload`.
4. Проверить firewall: `ufw status`, `iptables -L -n`. Если 80/443 закрыты — `ufw allow 80/tcp && ufw allow 443/tcp`.
5. Проверить DNS: `dig +short <domain>` должен совпадать с реальным IP сервера.
6. Если всё локально ок, но снаружи недоступно — проблема у провайдера или ISP.

#### Проверка

```bash
curl -I https://<domain>
curl -s https://<domain>/health
curl -s -o /dev/null -w '%{http_code}\n' https://<domain>/
```

Ожидаемо: `200 OK` на `/health`, `200` на главной.

#### Профилактика

- Внешний uptime monitor (UptimeRobot, Better Stack) с алертами.
- Healthcheck `/health` (см. [29-healthchecks](29-healthchecks.md)).
- `systemd Restart=on-failure` для nginx, php-fpm, postgresql, redis, messenger worker.
- Snapshot-резерв VPS у провайдера.
- Документированный план миграции на резервный VPS.

---

### 2. Nginx не отвечает

#### Симптомы

- `curl http://<domain>` возвращает `Connection refused` или таймаут.
- `systemctl status nginx` показывает `inactive (dead)` или `failed`.

#### Возможные причины

1. Сервис не запущен после перезагрузки (`systemctl enable nginx` не сделан).
2. Ошибка конфигурации после ручного редактирования или после deploy.
3. Конфликт порта 80/443 (другой процесс).
4. Поломка TLS-конфигурации / сертификата (см. инциденты 37–38).

#### Быстрая диагностика

```bash
systemctl status nginx --no-pager
journalctl -u nginx -n 200 --no-pager
nginx -t
ss -ltnp | grep -E ':80|:443'
ls -l /etc/nginx/sites-enabled/
```

#### Пошаговое решение

1. `nginx -t` — если показывает ошибку конфига, исправить указанную строку.
2. `journalctl -u nginx -n 200 --no-pager` — найти причину последнего падения.
3. Проверить, что порт 80/443 не занят другим процессом: `ss -ltnp | grep -E ':80|:443'`. Если занят — выяснить чем (`fuser -n tcp 80`).
4. Запустить: `systemctl start nginx`. Если не стартует — `systemctl reset-failed nginx && systemctl start nginx`.
5. Если только что был deploy — откатить vhost-файл из git/предыдущего релиза:
   `cp /var/www/<project>/releases/<previous>/tools/deploy/templates/nginx-production.conf /etc/nginx/sites-available/<project>` и `nginx -s reload`.

#### Проверка

```bash
systemctl status nginx --no-pager
nginx -t
curl -I https://<domain>
ss -ltnp | grep -E ':80|:443'
```

#### Профилактика

- `systemctl enable nginx`.
- Проверка `nginx -t` в deploy-скрипте перед reload.
- `Restart=on-failure` в override unit.
- CI gate, проверяющий валидность шаблона nginx-конфига.

---

### 3. Nginx отдаёт 502 Bad Gateway

#### Симптомы

- В браузере «502 Bad Gateway».
- `curl -I https://<domain>` → `HTTP/2 502`.
- В `/var/log/nginx/error.log` строки `connect() to unix:/run/php/php8.5-fpm.sock failed` или `upstream prematurely closed connection`.

#### Возможные причины

1. PHP-FPM не запущен / перезагружается.
2. PHP-FPM сегфолтит на запросе.
3. Превышен `max_execution_time` или `memory_limit`.
4. Кончились FPM-воркеры (`pm.max_children`).
5. Сокет указан неправильно или у nginx нет прав на сокет.
6. Ошибка в коде / fatal error в bootstrap (см. инцидент 7).

#### Быстрая диагностика

```bash
systemctl status php8.5-fpm --no-pager
journalctl -u php8.5-fpm -n 200 --no-pager
tail -n 200 /var/log/nginx/error.log
tail -n 200 /var/log/php8.5-fpm.log
tail -n 100 /var/www/<project>/current/var/log/prod.log
ls -l /run/php/
ss -lnp | grep php
```

Проверить нагрузку:

```bash
ps aux --sort=-%cpu | head
ps aux --sort=-%mem | head
free -m
```

#### Пошаговое решение

1. Если `php8.5-fpm` не запущен — `systemctl start php8.5-fpm`. Если не стартует — `journalctl -u php8.5-fpm -n 200`.
2. Если в логе nginx: `Permission denied` на сокет — `ls -l /run/php/php8.5-fpm.sock`, владелец должен быть `www-data`. Поправить `listen.owner` / `listen.group` в `/etc/php/8.5/fpm/pool.d/www.conf`, перезапустить.
3. Если в логе: `worker_connect_to_upstream timed out` — медленный код. Посмотреть `prod.log` за минуту до 502, искать долгие SQL/HTTP-вызовы.
4. Если кончились воркеры (в логе `server reached pm.max_children setting`) — временно увеличить `pm.max_children`, перезапустить:

   ```bash
   systemctl reload php8.5-fpm
   ```

5. Если только что был deploy — откатить релиз (см. инцидент 42).

#### Проверка

```bash
curl -I https://<domain>
curl -s https://<domain>/health
systemctl status php8.5-fpm --no-pager
tail -n 50 /var/log/nginx/error.log
```

Ожидаемо: 200 на `/health`, отсутствие новых `connect() failed` в error.log.

#### Профилактика

- Мониторинг `php-fpm` slow log (`request_slowlog_timeout = 5s`).
- Мониторинг занятых FPM workers (Prometheus/exporter или `pm.status_path`).
- Алерты на 5xx > 0.5% в access.log.
- `Restart=on-failure` для php-fpm.

---

### 4. Nginx отдаёт 403 Forbidden

#### Симптомы

- `curl -I https://<domain>/` → `403 Forbidden`.
- В `/var/log/nginx/error.log`: `directory index of "..." is forbidden` или `Permission denied`.

#### Возможные причины

1. Неправильный `root` в nginx-конфиге (указывает не на `public_html/`).
2. Файл `index.php` не существует в release.
3. У nginx нет прав на чтение файлов (chown/chmod).
4. Сломан symlink `current`.
5. После deploy `current` указывает на пустую папку.

#### Быстрая диагностика

```bash
ls -la /var/www/<project>/current/
ls -la /var/www/<project>/current/public_html/
readlink /var/www/<project>/current
nginx -T 2>/dev/null | grep -E 'root|server_name'
sudo -u www-data test -r /var/www/<project>/current/public_html/index.php && echo OK || echo NO_ACCESS
```

#### Пошаговое решение

1. Проверить, что `current` указывает на существующий релиз с файлами.
2. Проверить, что nginx `root` совпадает с `current/public_html`.
3. Если права не те:

   ```bash
   chown -R www-data:www-data /var/www/<project>/current/var
   chown -R www-data:www-data /var/www/<project>/current/public_html/build
   chmod -R u+rwX,g+rX,o+rX /var/www/<project>/current/public_html
   ```

4. Если `index.php` отсутствует — release собран некорректно, откатить (инцидент 42).

#### Проверка

```bash
curl -I https://<domain>/
curl -I https://<domain>/health
ls -la /var/www/<project>/current/public_html/index.php
```

#### Профилактика

- Шаг проверки `index.php` в deploy-скрипте.
- `tools/deploy/health-check.sh` после переключения `current`.
- Permissions фиксируются скриптом deploy (см. [34-deployment](34-deployment.md)).

---

### 5. Nginx отдаёт 404 для существующих страниц

#### Симптомы

- Главная отдаётся, но `/page-slug` возвращает `404 Not Found`.
- В `/var/log/nginx/access.log` — статус `404` для существующих URL.

#### Возможные причины

1. В nginx нет `try_files $uri /index.php$is_args$args;` — статика мимо Symfony.
2. Изменилась структура роутов, не сделан `cache:clear`/`cache:warmup`.
3. БД с контентом перепутана (указан другой `DATABASE_URL`).
4. Страницы не опубликованы (`published_at IS NULL`).
5. Кэш роутера/Twig содержит старые данные.

#### Быстрая диагностика

```bash
curl -I https://<domain>/<slug>
nginx -T 2>/dev/null | grep -A 10 'location /'
php bin/console debug:router | grep -i <slug>
php bin/console doctrine:query:sql "SELECT slug, status, published_at FROM page WHERE slug = '<slug>'"
tail -n 200 /var/log/nginx/access.log | grep '<slug>'
```

#### Пошаговое решение

1. Если в Symfony роут есть, но nginx отдаёт 404 — проверить блок `location /` в vhost.
2. Если роут не виден в `debug:router`:
   - `php bin/console cache:clear --env=prod --no-warmup`;
   - `php bin/console cache:warmup --env=prod`;
   - `systemctl reload php8.5-fpm`.
3. Проверить через SQL, что страница реально существует и опубликована.
4. Проверить `DATABASE_URL` в `shared/.env.local`.

#### Проверка

```bash
curl -I https://<domain>/<slug>
php bin/console debug:router | grep page
```

#### Профилактика

- Smoke test после deploy (`/`, `/sitemap.xml`, ключевые страницы).
- Регулярный `app:seo:audit` (см. [26-seo-architecture](26-seo-architecture.md)).

---

### 6. PHP-FPM не работает

#### Симптомы

- `systemctl status php8.5-fpm` → `failed` / `inactive`.
- Сайт отдаёт 502.
- В `journalctl` — fatal start error.

#### Возможные причины

1. Ошибка в `/etc/php/8.5/fpm/pool.d/www.conf`.
2. Не существует пользователь/группа из конфига пула.
3. Нет прав на `listen` сокет / папку.
4. Закончилась RAM / OOM убил процесс.
5. Сегфолт расширения PHP.

#### Быстрая диагностика

```bash
systemctl status php8.5-fpm --no-pager
journalctl -u php8.5-fpm -n 300 --no-pager
php-fpm8.5 -t
php -v
php -m
free -m
dmesg | tail -n 50
```

#### Пошаговое решение

1. `php-fpm8.5 -t` — если конфиг сломан, исправить указанный файл.
2. Если убит OOM — см. инцидент 47.
3. Если расширение сегфолтит — выявить какое (`php -m`, `journalctl`), временно отключить через `phpdismod <ext> && systemctl restart php8.5-fpm`.
4. После исправления — `systemctl restart php8.5-fpm`.

#### Проверка

```bash
systemctl status php8.5-fpm --no-pager
curl -I https://<domain>/health
ls -l /run/php/php8.5-fpm.sock
```

#### Профилактика

- `Restart=on-failure` в systemd.
- Алерт на `php-fpm` down.
- Алерт на OOM events (`grep -i 'killed process' /var/log/syslog`).

---

### 7. Symfony падает с ошибкой 500

#### Симптомы

- В браузере страница «Internal Server Error» (без debug-stacktrace, как и должно быть в prod).
- В `var/log/prod.log` записи уровня `ERROR` / `CRITICAL`.
- В `/var/log/nginx/access.log` — статусы `500`.

#### Возможные причины

1. Свежий релиз с багом.
2. Несовместимая миграция (поле удалено в БД, но код ещё его читает, или наоборот).
3. Недоступны зависимости: PostgreSQL, Redis, внешние HTTP API, SMTP.
4. Сломан `.env.local` / отсутствует обязательная переменная.
5. Permissions: `var/cache`, `var/log` не writable.
6. Неверно собран prod cache (`cache:warmup` упал).

#### Быстрая диагностика

```bash
tail -n 200 /var/www/<project>/current/var/log/prod.log
grep -E 'ERROR|CRITICAL' /var/www/<project>/current/var/log/prod.log | tail -n 50
php bin/console about
php bin/console doctrine:query:sql "SELECT 1"
redis-cli ping
ls -ld /var/www/<project>/current/var/cache /var/www/<project>/current/var/log
```

#### Пошаговое решение

1. По stacktrace в `prod.log` определить класс/файл.
2. Если связано с БД — проверить миграции (`doctrine:migrations:status`) и инцидент 14.
3. Если связано с Redis — инцидент 16.
4. Если связано с FS — инцидент 32.
5. Если ошибка появилась после deploy — откат (инцидент 42).
6. Не включать `APP_DEBUG=true` на production. Вместо этого — поднять полный stacktrace из `prod.log` или воспроизвести проблему на staging.

> **ВНИМАНИЕ.** Никогда не выставлять `APP_DEBUG=1` или `APP_ENV=dev` на production. Это раскрывает stacktrace, переменные окружения и `APP_SECRET` посетителям.

#### Проверка

```bash
curl -I https://<domain>/
curl -s https://<domain>/health
tail -f /var/www/<project>/current/var/log/prod.log
```

Ожидаемо: новых `ERROR` нет 5 минут подряд.

#### Профилактика

- CI gates: phpstan, phpunit, smoke (см. [35-cicd](35-cicd.md)).
- Staging deploy перед production.
- Алерты на ERROR/CRITICAL в Telegram-канал.
- Sentry / аналог (опционально).

---

### 8. Ошибки в `var/log/prod.log`

#### Симптомы

- Поток `ERROR` / `CRITICAL` в `prod.log`, при этом сайт может работать.

#### Возможные причины

1. Один из handler-ов падает на специфичных данных.
2. Внешний API недоступен.
3. Битый кэш (`cache.app`, `pools/`).
4. Неверные данные в БД после миграции.

#### Быстрая диагностика

```bash
tail -f /var/www/<project>/current/var/log/prod.log
grep -E 'ERROR|CRITICAL|EMERGENCY' /var/www/<project>/current/var/log/prod.log | tail -n 100
grep -c 'ERROR' /var/www/<project>/current/var/log/prod.log
awk '/ERROR/{print $0}' /var/www/<project>/current/var/log/prod.log | sort | uniq -c | sort -nr | head
```

#### Пошаговое решение

1. Сгруппировать ошибки по типу — взять самую частую.
2. Найти `request_id` (см. [28-logging-observability](28-logging-observability.md)) и собрать всю цепочку логов одного запроса:

   ```bash
   grep 'request_id=<id>' /var/www/<project>/current/var/log/prod.log
   ```

3. Если ошибка — `Connection refused` к Redis/PG/SMTP — переходить к соответствующему инциденту (16, 11, 59).
4. Если кэш «битый» — безопасно очистить:

   ```bash
   php bin/console cache:clear --env=prod --no-warmup
   php bin/console cache:warmup --env=prod
   systemctl reload php8.5-fpm
   ```

#### Проверка

```bash
tail -f /var/www/<project>/current/var/log/prod.log
```

Ожидаемо: ошибки прекратились или стали редкими.

#### Профилактика

- Логирование с `request_id` для трассировки.
- Алерт на скачок `ERROR/min`.
- Logrotate (инцидент 56) и Telegram-канал критики.

---

### 9. Неверный `APP_ENV` / `APP_DEBUG` в production

#### Симптомы

- На страницах виден web profiler / Symfony toolbar.
- При ошибке отображается полный stacktrace вместо «Internal Server Error».
- В `prod.log` нет ошибок, потому что приложение пишет в `dev.log`.

#### Возможные причины

1. После deploy `.env.local` указывает `APP_ENV=dev` / `APP_DEBUG=1`.
2. На сервере остался не подменённый файл `.env.local` из dev.
3. Symfony cache собран в `dev` env.

#### Быстрая диагностика

```bash
grep -E '^APP_(ENV|DEBUG)=' /var/www/<project>/shared/.env.local
php bin/console about | grep -E 'Environment|Debug'
ls -la /var/www/<project>/current/var/cache/
```

#### Пошаговое решение

1. Открыть `/var/www/<project>/shared/.env.local`, убедиться:

   ```bash
   APP_ENV=prod
   APP_DEBUG=0
   ```

2. Очистить и прогреть prod-кэш:

   ```bash
   php bin/console cache:clear --env=prod --no-warmup
   php bin/console cache:warmup --env=prod
   systemctl reload php8.5-fpm
   ```

3. Проверить, что Symfony cache в `var/cache/prod/`, а не `var/cache/dev/`.

> **ВНИМАНИЕ.** Если `APP_DEBUG=1` был включён хоть на минуту — считать, что секреты потенциально утекли в логи и кэш браузеров/CDN. Ротация `APP_SECRET` и других чувствительных переменных — обязательна.

#### Проверка

```bash
curl -I https://<domain>/_profiler   # должно быть 404, не 200
php bin/console about | grep -E 'Environment|Debug'
```

#### Профилактика

- Шаблон `.env.production.example` с зафиксированными `APP_ENV=prod`, `APP_DEBUG=0`.
- CI/deploy проверка: `grep -E '^APP_(ENV|DEBUG)=' shared/.env.local`.
- Запрет роута `/_profiler` в production-vhost.

---

### 10. Неверный `.env.local` или отсутствуют переменные окружения

#### Симптомы

- 500 на любой странице.
- В `prod.log`: `RuntimeException: Environment variable not found: "..."`.
- `php bin/console about` падает.

#### Возможные причины

1. Не симлинкнут `shared/.env.local` в текущий релиз.
2. После rollout добавлена новая обязательная переменная, но в shared её нет.
3. Сломан синтаксис `.env.local` (несбалансированные кавычки).

#### Быстрая диагностика

```bash
ls -la /var/www/<project>/current/.env.local
readlink /var/www/<project>/current/.env.local
cat -A /var/www/<project>/shared/.env.local | head -n 50
php bin/console debug:dotenv
php bin/console about
```

#### Пошаговое решение

1. Если symlink отсутствует:

   ```bash
   ln -sfn /var/www/<project>/shared/.env.local /var/www/<project>/current/.env.local
   ```

2. Если переменная отсутствует — добавить в `shared/.env.local`. Проверить по списку из [27-config-and-env](27-config-and-env.md).
3. После правок:

   ```bash
   php bin/console cache:clear --env=prod --no-warmup
   php bin/console cache:warmup --env=prod
   systemctl reload php8.5-fpm
   systemctl restart <project>-messenger
   ```

#### Проверка

```bash
php bin/console debug:dotenv | head -n 30
php bin/console about
curl -s https://<domain>/health
```

#### Профилактика

- Сравнение `shared/.env.local` с `.env.production.example` в pre-deploy шаге.
- `tools/deploy/...` падает, если есть необъявленные обязательные переменные.

---

### 11. PostgreSQL недоступен

#### Симптомы

- 500 на сайте, в `prod.log`: `SQLSTATE[08006]` / `could not connect to server`.
- `systemctl status postgresql` → `failed` или `inactive`.

#### Возможные причины

1. Сервис упал / OOM.
2. Кончилось место на диске под `pg_wal`.
3. Сломан `postgresql.conf` / `pg_hba.conf` после правки.
4. Закончились коннекты (`max_connections`).

#### Быстрая диагностика

```bash
systemctl status postgresql --no-pager
journalctl -u postgresql -n 200 --no-pager
sudo -u postgres psql -c "SELECT version();"
sudo -u postgres psql -c "SELECT count(*) FROM pg_stat_activity;"
df -h
ls /var/lib/postgresql/18/main/pg_wal | wc -l
psql --version
```

#### Пошаговое решение

1. Если сервис не запущен — `systemctl start postgresql`.
2. Если стартует и падает — `journalctl -u postgresql`.
3. Если диск полон — освободить место (см. инцидент 46), **только потом** старт PG.
4. Если `too many connections` — найти приложение, открывшее сотни коннектов:

   ```bash
   sudo -u postgres psql -c "SELECT pid, state, application_name, query FROM pg_stat_activity ORDER BY query_start;"
   ```

   Перезапустить виновника (php-fpm / messenger), не перезагружать PG, если можно избежать.

> **ВНИМАНИЕ.** Не запускать `pg_resetwal`, `--drop-database`, никаких `rm -rf /var/lib/postgresql` без участия DBA и подтверждённого свежего бэкапа.

#### Проверка

```bash
systemctl status postgresql --no-pager
sudo -u postgres psql -c "SELECT 1;"
php bin/console doctrine:query:sql "SELECT 1"
curl -s https://<domain>/health
```

#### Профилактика

- Алерт на `postgresql` down.
- Мониторинг свободного места (≥ 20%).
- Мониторинг `pg_stat_activity.count` и долгих запросов.
- Корректные `max_connections` и pool settings в Doctrine.

---

### 12. Ошибка подключения к базе данных

#### Симптомы

- В `prod.log`: `Connection refused`, `password authentication failed`, `database "..." does not exist`.
- 500 на сайте, при этом `systemctl status postgresql` — `active (running)`.

#### Возможные причины

1. Неверный `DATABASE_URL` в `shared/.env.local` (host, port, user, password, db).
2. После ротации пароля БД старый пароль остался в env.
3. `pg_hba.conf` не разрешает доступ с `127.0.0.1` для нашего user.
4. БД переименована / удалена.

#### Быстрая диагностика

```bash
grep DATABASE_URL /var/www/<project>/shared/.env.local
php bin/console doctrine:query:sql "SELECT current_database(), current_user, version();"
sudo -u postgres psql -c "\l"
sudo -u postgres psql -c "\du"
sudo cat /etc/postgresql/18/main/pg_hba.conf | grep -v '^#'
```

#### Пошаговое решение

1. Проверить, что `DATABASE_URL` соответствует реальной БД и user.
2. Проверить пароль: `psql "postgresql://<user>:<pass>@127.0.0.1:5432/<db_name>" -c "SELECT 1"`.
3. Если пароль нужно сбросить:

   ```bash
   sudo -u postgres psql -c "ALTER USER <user> WITH PASSWORD '<new_pass>';"
   ```

   обновить `DATABASE_URL`, очистить кэш, reload php-fpm.

#### Проверка

```bash
php bin/console doctrine:query:sql "SELECT 1"
curl -s https://<domain>/health
```

#### Профилактика

- Сразу после ротации пароля — проверка из приложения.
- В мониторинге — отдельный probe «приложение видит БД».

---

### 13. База данных переполнена или слишком большая

#### Симптомы

- Растущее место на диске в `/var/lib/postgresql/`.
- Запросы становятся медленнее.
- Алерт «PostgreSQL data dir > X GB».

#### Возможные причины

1. Не настроен `autovacuum` или таблицы заброшены.
2. Большие таблицы без архивации (логи, события, временные данные).
3. Накапливаются записи в `messenger_messages` / `failed`.
4. Огромные индексы / битые индексы.

#### Быстрая диагностика

```bash
df -h
sudo du -sh /var/lib/postgresql/18/main/
sudo -u postgres psql -d <db_name> -c "
SELECT relname, pg_size_pretty(pg_total_relation_size(relid)) AS size
FROM pg_catalog.pg_statio_user_tables
ORDER BY pg_total_relation_size(relid) DESC LIMIT 20;"
sudo -u postgres psql -d <db_name> -c "SELECT count(*) FROM messenger_messages;"
```

#### Пошаговое решение

1. Найти крупные таблицы — оценить, нужны ли все данные.
2. Очистить старые записи (по согласованию с командой):

   ```bash
   php bin/console messenger:failed:remove --force <id>
   ```

3. Запустить `VACUUM FULL` на конкретной таблице **только в окно обслуживания** (он блокирует таблицу):

   > **ВНИМАНИЕ.** `VACUUM FULL` блокирует таблицу. Делать только в maintenance window и со свежим бэкапом.

   ```bash
   sudo -u postgres psql -d <db_name> -c "VACUUM (VERBOSE, ANALYZE) <table>;"
   ```

4. Если индексы раздулись — `REINDEX TABLE CONCURRENTLY <table>;`.

#### Проверка

```bash
df -h
sudo du -sh /var/lib/postgresql/18/main/
```

#### Профилактика

- Включён `autovacuum` (по умолчанию).
- Регулярная архивация старых логов/событий.
- Мониторинг размера БД и роста.

---

### 14. Doctrine migrations failed

#### Симптомы

- Deploy упал на шаге `doctrine:migrations:migrate`.
- В выводе: `Migration X failed during ...`.
- `doctrine:migrations:status` показывает «Available, not yet executed» или «Executed, but not registered».

#### Возможные причины

1. Конфликт изменений (одна и та же колонка в нескольких миграциях).
2. SQL ошибка (синтаксис, FK, NOT NULL без default на непустой таблице).
3. Lock на таблицу длительной транзакцией.
4. Превышен `lock_timeout` / `statement_timeout`.

#### Быстрая диагностика

```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:list
php bin/console doctrine:migrations:status --show-versions | head -n 50
sudo -u postgres psql -d <db_name> -c "SELECT * FROM doctrine_migration_versions ORDER BY executed_at DESC LIMIT 20;"
sudo -u postgres psql -d <db_name> -c "SELECT pid, query, state, wait_event FROM pg_stat_activity WHERE state <> 'idle';"
```

#### Пошаговое решение

1. Прочитать SQL миграции в `migrations/Version<TS>.php`.
2. Если есть зависшая транзакция — найти `pid` и аккуратно завершить:

   ```bash
   sudo -u postgres psql -c "SELECT pg_terminate_backend(<pid>);"
   ```

3. Если SQL миграции явно неверен — НЕ применять «вручную», поправить миграцию и собрать новый release. Перейти к инциденту 15 (частичное применение).
4. После исправления — повторить миграцию:

   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

> **ВНИМАНИЕ.** Любые ручные SQL правки на production делать только после `pg_dump` и только если миграция не применится повторно.

#### Проверка

```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:query:sql "SELECT 1"
curl -s https://<domain>/health
```

#### Профилактика

- Прогон миграций на staging перед production.
- Чек на `lock_timeout` / `statement_timeout` в Doctrine конфиге.
- Не делать deploy в час пик.
- См. [18-migrations](18-migrations.md).

---

### 15. Миграции применились частично

#### Симптомы

- `doctrine:migrations:status` показывает несоответствие между «Available» и «Executed».
- Часть DDL применилась, часть — нет.
- В `doctrine_migration_versions` версия не записана, но изменения в БД присутствуют.

#### Возможные причины

1. Не транзакционная миграция упала посередине (часть DDL/DML уже применена).
2. Соединение с БД оборвалось во время `migrations:migrate`.
3. PostgreSQL не поддерживает транзакционный DDL для конкретных операций (например, `CREATE INDEX CONCURRENTLY`).

#### Быстрая диагностика

```bash
php bin/console doctrine:migrations:status
sudo -u postgres psql -d <db_name> -c "SELECT version, executed_at FROM doctrine_migration_versions ORDER BY executed_at DESC LIMIT 20;"
sudo -u postgres psql -d <db_name> -c "\d <table_with_changes>"
```

#### Пошаговое решение

> **ВНИМАНИЕ.** Это самый опасный сценарий runbook. Любые действия — только со свежим backup.

1. Снять полный бэкап БД немедленно:

   ```bash
   PGPASSWORD=... pg_dump -h 127.0.0.1 -U <user> -d <db_name> --format=custom --file=/var/www/<project>/shared/backups/db/<db_name>-pre-fix-$(date +%Y%m%d-%H%M%S).dump
   ```

2. Включить maintenance mode (см. инциденты 62/63), остановить worker.
3. Выбрать стратегию:
   - **Доручную довести миграцию**: применить недостающие SQL вручную, затем зафиксировать миграцию:

     ```bash
     php bin/console doctrine:migrations:version <FQCN_or_version> --add --no-interaction
     ```

   - **Откатить** уже применённые SQL (если возможно) и повторить миграцию заново.
   - **Restore из backup** до момента до миграции (см. [36-backup-restore](36-backup-restore.md)).
4. Проверить идемпотентность: `doctrine:migrations:status` чистый, smoke-тесты проходят.
5. Снять maintenance mode.

#### Проверка

```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:query:sql "SELECT 1"
curl -s https://<domain>/health
```

#### Профилактика

- Все миграции — с явными транзакциями там, где PG это позволяет.
- Долгие операции — отдельные миграции (`CREATE INDEX CONCURRENTLY`).
- Смотри [18-migrations](18-migrations.md), правила «zero-downtime migrations».

---

### 16. Redis недоступен

#### Симптомы

- В `prod.log`: `Connection refused`, `RedisException: Connection lost`.
- Просел latency, кэш-промахи 100%.
- `systemctl status redis-server` → `failed`.

#### Возможные причины

1. Сервис упал / OOM.
2. Превышен `maxmemory`, eviction policy не настроена.
3. Сломан `redis.conf`.
4. Неверный `REDIS_URL`/`REDIS_PASSWORD` в env.
5. AOF файл повреждён.

#### Быстрая диагностика

```bash
systemctl status redis-server --no-pager
journalctl -u redis-server -n 200 --no-pager
redis-cli ping
redis-cli info server
redis-cli info memory
redis-cli info clients
grep REDIS /var/www/<project>/shared/.env.local
```

#### Пошаговое решение

1. Если Redis не отвечает — `systemctl restart redis-server`.
2. Если падает на старте по AOF — посмотреть `journalctl`. В крайнем случае выключить AOF в `redis.conf` (`appendonly no`) и перезапустить, затем восстановить из RDB.

   > **ВНИМАНИЕ.** Отключение AOF теряет последние записи между snapshot’ами. В нашем стеке Redis — это кэш и Doctrine messenger не использует Redis, поэтому потеря допустима, но фиксируйте инцидент.

3. Если `REDIS_URL` неверен — поправить `shared/.env.local`, очистить cache, reload php-fpm.

#### Проверка

```bash
redis-cli ping             # PONG
redis-cli info clients
curl -s https://<domain>/health
tail -n 50 /var/www/<project>/current/var/log/prod.log
```

#### Профилактика

- `Restart=on-failure`.
- `maxmemory` + `maxmemory-policy allkeys-lru` (см. [23-cache-and-redis](23-cache-and-redis.md)).
- Алерт на Redis down.

---

### 17. Symfony Cache не работает

#### Симптомы

- В `prod.log`: `CacheException`, ошибки сериализации.
- Долгие ответы (всё считается заново).
- Ошибки в `var/cache/prod/`.

#### Возможные причины

1. Pool настроен на Redis, а Redis недоступен (см. инцидент 16).
2. Permissions на `var/cache/`.
3. Битые файлы кэша после некорректного релиза.
4. Отсутствует `cache:warmup` после deploy.

#### Быстрая диагностика

```bash
ls -ld /var/www/<project>/current/var/cache /var/www/<project>/current/var/cache/prod
php bin/console cache:pool:list
php bin/console cache:pool:prune
redis-cli ping
```

#### Пошаговое решение

1. Если проблема в Redis — инцидент 16.
2. Если проблема в FS — права:

   ```bash
   chown -R www-data:www-data /var/www/<project>/current/var
   chmod -R u+rwX,g+rwX /var/www/<project>/current/var
   ```

3. Безопасный пересбор кэша — см. инцидент 18.

#### Проверка

```bash
php bin/console cache:pool:list
curl -s https://<domain>/health
tail -n 50 /var/www/<project>/current/var/log/prod.log
```

#### Профилактика

- `cache:warmup` в каждом релизе.
- Мониторинг hit-rate Redis.

---

### 18. Нужно безопасно очистить cache

#### Симптомы

- Подозрение на устаревший роут/Twig/конфиг.
- После hot-fix файла на сервере (это плохо, но иногда бывает).

#### Возможные причины

- Локальный edit без deploy.
- Проблема инвалидации.

#### Быстрая диагностика

```bash
ls -la /var/www/<project>/current/var/cache/
php bin/console cache:pool:list
```

#### Пошаговое решение

1. Стандартный безопасный путь:

   ```bash
   cd /var/www/<project>/current
   php bin/console cache:clear --env=prod --no-warmup
   php bin/console cache:warmup --env=prod
   chown -R www-data:www-data var/cache
   systemctl reload php8.5-fpm
   ```

2. Очистить cache pool в Redis:

   ```bash
   php bin/console cache:pool:clear cache.app
   php bin/console cache:pool:clear cache.system
   ```

3. Никогда не делать `rm -rf var/cache/*` под `www-data`/без проверки путей.

   > **ВНИМАНИЕ.** `rm -rf var/cache` опасно, если случайно выполнено в `/`, в shared, или вне current. Только из `/var/www/<project>/current/`.

#### Проверка

```bash
php bin/console about
curl -s https://<domain>/health
tail -n 50 /var/www/<project>/current/var/log/prod.log
```

#### Профилактика

- Не редактировать файлы напрямую на проде. Всегда — через deploy.

---

### 19. Очереди Messenger не обрабатываются

#### Симптомы

- Сообщения копятся в БД (`messenger_messages`).
- Email’ы / Telegram-логи не отправляются.
- `messenger:stats` показывает рост.

#### Возможные причины

1. Worker не запущен (`<project>-messenger`).
2. Worker завис (см. инцидент 20).
3. Handler кидает исключение и письма уходят в `failed` (инцидент 22).
4. Нет соединения с БД у worker’а.

#### Быстрая диагностика

```bash
systemctl status <project>-messenger --no-pager
journalctl -u <project>-messenger -n 200 --no-pager
php bin/console messenger:stats
sudo -u postgres psql -d <db_name> -c "SELECT queue_name, count(*) FROM messenger_messages GROUP BY queue_name;"
```

#### Пошаговое решение

1. Если unit `inactive`/`failed` — `systemctl start <project>-messenger`.
2. Если падает на старте — посмотреть `journalctl`, исправить, `systemctl restart`.
3. Если работает, но очередь растёт — проверить throughput, увеличить количество воркеров (запуск нескольких instance’ов через template unit).

#### Проверка

```bash
php bin/console messenger:stats
journalctl -u <project>-messenger -n 50 --no-pager
```

#### Профилактика

- `Restart=always` в systemd.
- Алерт на длину очереди.
- См. [24-messenger-and-queues](24-messenger-and-queues.md).

---

### 20. Worker завис или не завершает задачи

#### Симптомы

- Процесс worker’а присутствует (`ps aux | grep messenger:consume`), но не двигает очередь.
- Высокий CPU/RAM у одного процесса PHP.

#### Возможные причины

1. Бесконечный цикл в handler.
2. Заблокированный SQL (long-running transaction).
3. Сторонний HTTP-вызов без таймаута.

#### Быстрая диагностика

```bash
ps -ef | grep messenger:consume
journalctl -u <project>-messenger -n 200 --no-pager
sudo -u postgres psql -c "SELECT pid, state, wait_event, query FROM pg_stat_activity WHERE application_name LIKE '%consume%';"
```

#### Пошаговое решение

1. Безопасный путь — рестарт worker:

   ```bash
   systemctl restart <project>-messenger
   ```

   Это «мягкий» путь: messenger ack’нется только при успешной обработке, остальное вернётся в очередь.
2. Если зависание системное — проверить handler, добавить таймауты в HTTP/SMTP клиенты.

#### Проверка

```bash
systemctl status <project>-messenger --no-pager
php bin/console messenger:stats
```

#### Профилактика

- `--time-limit` и `--memory-limit` у `messenger:consume`.
- HTTP-клиенты с таймаутами по умолчанию.

---

### 21. Слишком много сообщений в очереди

#### Симптомы

- `messenger:stats` показывает тысячи сообщений.
- Лаг доставки писем растёт.

#### Возможные причины

1. Всплеск отправки (массовая рассылка / лиды).
2. Падает внешний сервис, retry увеличивает очередь.
3. Один worker не справляется.

#### Быстрая диагностика

```bash
php bin/console messenger:stats
sudo -u postgres psql -d <db_name> -c "SELECT queue_name, count(*) FROM messenger_messages GROUP BY queue_name;"
journalctl -u <project>-messenger -n 200 --no-pager
```

#### Пошаговое решение

1. Поднять количество одновременных воркеров: запустить несколько systemd unit’ов (или templated `@1`, `@2`).
2. Если очередь растёт из-за внешнего сбоя — временно поставить лимит на отправку или временно отключить транспорт, накопить позже.
3. Не выключать worker без понимания: сообщения останутся в БД.

#### Проверка

```bash
php bin/console messenger:stats
```

#### Профилактика

- Алерт на длину очереди > N.
- Capacity test.

---

### 22. Failed messages растут

#### Симптомы

- В `messenger:failed:show` — десятки/сотни сообщений.
- В Telegram-канале или Sentry — поток ошибок handler’ов.

#### Возможные причины

1. Баг в handler.
2. Внешний сервис отказал.
3. Изменился формат сообщений после deploy.

#### Быстрая диагностика

```bash
php bin/console messenger:failed:show
php bin/console messenger:failed:show <id>      # детали одного
journalctl -u <project>-messenger -n 200 --no-pager
```

#### Пошаговое решение

1. Прочитать stacktrace конкретного failed message.
2. Если причина — временный сбой, попробовать retry:

   ```bash
   php bin/console messenger:failed:retry --force
   ```

3. Если сообщение «битое» / устаревшее — удалить:

   > **ВНИМАНИЕ.** `messenger:failed:remove` удаляет данные сообщения безвозвратно.

   ```bash
   php bin/console messenger:failed:remove <id>
   ```

4. Если массовая ошибка из-за бага — починить код, deploy, потом `messenger:failed:retry`.

#### Проверка

```bash
php bin/console messenger:failed:show
php bin/console messenger:stats
```

#### Профилактика

- Алерт на failed > 0.
- Идемпотентные handler’ы.

---

### 23. Нужно перезапустить workers без потери задач

#### Симптомы

- Плановый рестарт после изменения env / handler / deps.

#### Возможные причины

- Любой deploy / hot-fix зависимости / смена env.

#### Быстрая диагностика

```bash
systemctl status <project>-messenger --no-pager
ps -ef | grep messenger:consume
```

#### Пошаговое решение

1. Сначала «попросить» worker’ы корректно завершиться:

   ```bash
   php bin/console messenger:stop-workers
   ```

   Это пометит cache, и работающие consumer’ы выйдут после текущего сообщения.
2. Затем — перезапуск:

   ```bash
   systemctl restart <project>-messenger
   ```

3. Никогда не убивать worker через `kill -9` без необходимости — это может оставить сообщение в state «processing».

#### Проверка

```bash
systemctl status <project>-messenger --no-pager
php bin/console messenger:stats
journalctl -u <project>-messenger -n 50 --no-pager
```

#### Профилактика

- Использовать `--time-limit` чтобы worker’ы регулярно сами выходили и подбирали свежие настройки.

---

### 24. Frontend assets не собрались

#### Симптомы

- В `npm run build` — ошибка.
- Deploy упал на шаге сборки фронта.

#### Возможные причины

1. Несовместимые версии Node/npm.
2. Обновлены зависимости, lockfile рассинхронизирован.
3. Не хватает RAM на сборку.
4. Битые исходники / синтаксис.

#### Быстрая диагностика

```bash
node -v
npm -v
cat package.json | head
ls -la package-lock.json
free -m
df -h
```

#### Пошаговое решение

1. На сервере: `npm ci` (только из `package-lock.json`, без `npm install`).
2. Если падает по памяти — `NODE_OPTIONS=--max-old-space-size=2048 npm run build`.
3. Если ошибка в коде — собирать ассеты в CI и копировать готовый `public_html/build/`, а не собирать на проде (см. [22-frontend-assets](22-frontend-assets.md), [35-cicd](35-cicd.md)).

> **ВНИМАНИЕ.** На production нельзя `npm install` без lockfile — обновит зависимости неконтролируемо.

#### Проверка

```bash
ls -la /var/www/<project>/current/public_html/build/
test -f /var/www/<project>/current/public_html/build/manifest.json && echo OK
```

#### Профилактика

- Сборка ассетов в CI, артефакт прицепляется к release.
- Зафиксированные `engines` в `package.json`.

---

### 25. Vite manifest отсутствует

#### Симптомы

- 500 на любой странице, `Asset manifest not found`.
- В `prod.log`: исключение про `manifest.json`.

#### Возможные причины

1. `npm run build` не запускался.
2. Permissions на `public_html/build/`.
3. После deploy `current` указывает на release без сборки.

#### Быстрая диагностика

```bash
ls -la /var/www/<project>/current/public_html/build/
test -f /var/www/<project>/current/public_html/build/manifest.json && echo OK || echo MISSING
ls -la /var/www/<project>/current/public_html/build/manifest.json
```

#### Пошаговое решение

1. Запустить сборку из текущего release:

   ```bash
   cd /var/www/<project>/current
   npm ci
   npm run build
   chown -R www-data:www-data public_html/build
   ```

2. Если на сервере нет Node — собрать локально/в CI и `rsync` в `public_html/build/`.

#### Проверка

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://<domain>/build/manifest.json
curl -I https://<domain>/
```

#### Профилактика

- В deploy-скрипте — обязательная проверка существования `manifest.json` перед переключением `current`.

---

### 26. CSS/JS не обновились после deploy

#### Симптомы

- Пользователи видят старые стили.
- `view-source` показывает старые имена бандлов.

#### Возможные причины

1. Браузерный/CDN кэш.
2. Не сделан `cache:clear`/`cache:warmup`.
3. Не сделан `systemctl reload php8.5-fpm` (OPcache).
4. Symfony Asset не использует Vite manifest.

#### Быстрая диагностика

```bash
curl -I https://<domain>/build/manifest.json
curl -s https://<domain>/build/manifest.json | head
ls -la /var/www/<project>/current/public_html/build/
```

#### Пошаговое решение

1. Очистить и прогреть Symfony cache:

   ```bash
   php bin/console cache:clear --env=prod --no-warmup
   php bin/console cache:warmup --env=prod
   systemctl reload php8.5-fpm
   ```

2. В nginx для `/build/` должен быть immutable cache. Сами файлы — с хэшем в имени, поэтому браузер автоматически подхватит новые.
3. Если проблема в CDN — инвалидация CDN-кэша.

#### Проверка

```bash
curl -I https://<domain>/build/<hashed-file>
curl -s https://<domain>/ | grep build/
```

#### Профилактика

- Hashed asset names (Vite по умолчанию).
- В nginx: `Cache-Control: public, immutable, max-age=31536000` для `/build/*`.

---

### 27. Admin area недоступна

#### Симптомы

- `/admin/...` — 500 / 404 / редирект в цикл.

#### Возможные причины

1. Не загружены роуты / не сделан `cache:warmup`.
2. Сломан Vue admin entrypoint (manifest, см. инцидент 25).
3. Security firewall неверно сконфигурен.
4. Cookies/сессия в Redis недоступна.

#### Быстрая диагностика

```bash
curl -I https://<domain>/admin
php bin/console debug:router | grep admin
php bin/console debug:firewall
tail -n 100 /var/www/<project>/current/var/log/prod.log
```

#### Пошаговое решение

1. Прогреть кэш, перезагрузить fpm.
2. Если manifest.json отсутствует — инцидент 25.
3. Если security misconfigured — откатить релиз (инцидент 42), исправить и заново.

#### Проверка

```bash
curl -I https://<domain>/admin/login
```

#### Профилактика

- Smoke-тест `/admin/login` после deploy.

---

### 28. Ошибки авторизации / login не работает

#### Симптомы

- Пользователь не может войти, форма возвращает «Неверный логин/пароль» при правильных данных.
- Сессия теряется сразу после логина.

#### Возможные причины

1. Неверный `APP_SECRET` (изменился, инвалидируя cookies/CSRF-токены).
2. Каталог сессий (`var/sessions/<env>/` или иной `save_path`) недоступен на запись (permissions, full disk).
3. Часы сервера расходятся (token expiry / cookie expiry).
4. CSRF-токен (см. инцидент 29).
5. Изменился `password_hashers` алгоритм или migrate-on-login завершился ошибкой.
6. `login_throttling` заблокировал IP/identifier (5 попыток / 15 минут).
7. Сессии планово вынесены в Redis (целевое, см. [ADR-0007](../adr/0007-redis-cache-and-messenger.md) и [20-security-and-access-control](../20-security-and-access-control.md#session)) и Redis недоступен — на текущем стеке **не применимо**, фактически сессии хранятся в файлах.

#### Быстрая диагностика

```bash
date
timedatectl
ls -ld /var/www/<project>/current/var/sessions/prod 2>/dev/null
df -h /var/www/<project>
grep -E 'APP_SECRET' /var/www/<project>/shared/.env.local
php bin/console debug:firewall
tail -n 200 /var/www/<project>/current/var/log/prod.log | grep -iE 'security|login'
```

#### Пошаговое решение

1. `timedatectl` — проверить, что время синхронизировано (`NTP=active`).
2. Проверить, что `APP_SECRET` стабилен между релизами.
3. Проверить, что Redis жив, сессии не теряются.
4. Если сменили алгоритм password hash — потребуется ре-хэш паролей при следующем входе (Symfony Security умеет авто-rehash).

#### Проверка

- Залогиниться тестовым пользователем.
- В логе нет `BadCredentialsException` для валидных credentials.

#### Профилактика

- `APP_SECRET` не меняется без ротации сессий и уведомления.
- Чёткий алгоритм хэширования.

---

### 29. CSRF ошибки

#### Симптомы

- При POST из формы — «Invalid CSRF token» / 400.

#### Возможные причины

1. Сессия истекла / потеряна (см. инцидент 28).
2. Открыта старая вкладка.
3. Неверно настроен `framework.csrf_protection`.
4. nginx режет cookie `_csrf` или slim’ит cookies.

#### Быстрая диагностика

```bash
tail -n 100 /var/www/<project>/current/var/log/prod.log | grep -i csrf
curl -I https://<domain>/admin/login
```

#### Пошаговое решение

1. Если массово — проверить session storage (Redis).
2. Если у одного пользователя — попросить почистить cookies / hard-refresh.
3. Не отключать CSRF защиту в production.

#### Проверка

- Нормальный логин и POST форма работают.

#### Профилактика

- Long-lived `_csrf` cookie + защита через samesite/secure.

---

### 30. Загрузка файлов не работает

#### Симптомы

- Админка пишет «Не удалось сохранить файл», 500 при загрузке.
- В `prod.log`: `Permission denied` / `No space left on device` / `MaxUploadSizeExceeded`.

#### Возможные причины

1. Permissions на `public_html/uploads/`.
2. `client_max_body_size` в nginx меньше размера файла.
3. `upload_max_filesize` / `post_max_size` в `php.ini`.
4. Кончилось место на диске.

#### Быстрая диагностика

```bash
df -h
ls -ld /var/www/<project>/current/public_html/uploads
ls -ld /var/www/<project>/shared/public_html/uploads
sudo -u www-data test -w /var/www/<project>/current/public_html/uploads && echo OK || echo NO_WRITE
nginx -T 2>/dev/null | grep -i client_max_body_size
php -i | grep -E 'upload_max_filesize|post_max_size|memory_limit'
```

#### Пошаговое решение

1. Если permissions — `chown -R www-data:www-data /var/www/<project>/shared/public_html/uploads`.
2. Если nginx режет — поднять `client_max_body_size 50M;` (или сколько нужно), `nginx -t && systemctl reload nginx`.
3. Если PHP режет — поправить `upload_max_filesize`, `post_max_size`, `memory_limit`, перезагрузить fpm.
4. Если диск полон — инцидент 46.

#### Проверка

- Загрузка файла из админки проходит.
- В `prod.log` нет ошибок upload.

#### Профилактика

- См. [25-files-and-uploads](25-files-and-uploads.md).
- Алерты на свободное место.

---

### 31. Uploads не отдаются через nginx

#### Симптомы

- Файл загружается, но `https://<domain>/uploads/<file>` отдаёт 404 или 403.

#### Возможные причины

1. Symlink `current/public_html/uploads -> shared/public_html/uploads` сломан.
2. Permissions на shared.
3. nginx не обслуживает `/uploads/` (location).
4. Запрет исполнения PHP внутри `/uploads/` ошибочно ловит обычные файлы.

#### Быстрая диагностика

```bash
ls -la /var/www/<project>/current/public_html/uploads
ls -la /var/www/<project>/shared/public_html/uploads
readlink /var/www/<project>/current/public_html/uploads
nginx -T 2>/dev/null | grep -A 3 'location /uploads'
curl -I https://<domain>/uploads/<known-file>
```

#### Пошаговое решение

1. Проверить symlink, при необходимости пересоздать.
2. Permissions: `chown -R www-data:www-data /var/www/<project>/shared/public_html/uploads`.
3. nginx должен иметь:

   ```nginx
   location /uploads/ {
       try_files $uri =404;
       add_header X-Content-Type-Options nosniff;
       location ~* \.php$ { return 403; }
   }
   ```

4. `nginx -t && systemctl reload nginx`.

#### Проверка

```bash
curl -I https://<domain>/uploads/<known-file>
```

#### Профилактика

- Шаги создания symlink — в deploy-скрипте.

---

### 32. Ошибки прав доступа к `var/`, `public/uploads/`, cache/, logs/

#### Симптомы

- `Permission denied` в `prod.log`.
- `cache:warmup` падает с ошибкой записи.

#### Возможные причины

1. Запуск console под `root` создал файлы, которые `www-data` не может перезаписать.
2. После rsync владельцы сбились.
3. После переключения `current` забыли `chown`.

#### Быстрая диагностика

```bash
ls -ld /var/www/<project>/current/var
ls -ld /var/www/<project>/current/var/cache
ls -ld /var/www/<project>/current/var/log
ls -ld /var/www/<project>/current/public_html/build
ls -ld /var/www/<project>/shared/public_html/uploads
sudo -u www-data test -w /var/www/<project>/current/var/cache && echo OK
```

#### Пошаговое решение

```bash
chown -R www-data:www-data /var/www/<project>/current/var
chown -R www-data:www-data /var/www/<project>/current/public_html/build
chown -R www-data:www-data /var/www/<project>/shared/public_html/uploads
chown -R www-data:www-data /var/www/<project>/shared/var/log
chmod -R u+rwX,g+rwX /var/www/<project>/current/var
chmod 640 /var/www/<project>/shared/.env.local
```

> **ВНИМАНИЕ.** Не выставлять `chmod 777`. Это безопасностный антипаттерн.

#### Проверка

```bash
sudo -u www-data php bin/console cache:warmup --env=prod
tail -n 50 /var/www/<project>/current/var/log/prod.log
```

#### Профилактика

- Никогда не запускать `php bin/console` под `root` на production.
- Permissions фиксируются deploy-скриптом.

---

### 33. SEO-страницы отдают неправильные статусы

#### Симптомы

- Страница, которая должна 200 — отдаёт 404, 301, 302 или 410.
- В Google Search Console — рост ошибок индексации.

#### Возможные причины

1. Неверные правила redirect.
2. Кэш роутера.
3. Изменён slug страницы.
4. Условие публикации не выполнено.

#### Быстрая диагностика

```bash
curl -I https://<domain>/<slug>
curl -sIL https://<domain>/<slug> | grep -E 'HTTP/|Location:'
php bin/console debug:router | grep <slug>
php bin/console doctrine:query:sql "SELECT slug, status, published_at FROM page WHERE slug = '<slug>'"
```

#### Пошаговое решение

1. Прогреть кэш.
2. Проверить таблицу redirects (если есть в проекте).
3. Если страница реально снята с публикации — это ожидаемо; согласовать с продуктом.

#### Проверка

```bash
curl -I https://<domain>/<slug>
```

#### Профилактика

- См. [26-seo-architecture](26-seo-architecture.md).
- Регулярный `app:seo:audit`.

---

### 34. Sitemap не генерируется или отдаёт ошибку

#### Симптомы

- `/sitemap.xml` отдаёт 404 / 500.
- В Search Console — «Sitemap could not be read».

#### Возможные причины

1. Команда генерации не запускалась.
2. Ошибка в handler.
3. Permissions на файл/директорию вывода.

#### Быстрая диагностика

```bash
curl -I https://<domain>/sitemap.xml
php bin/console list | grep -i sitemap
tail -n 100 /var/www/<project>/current/var/log/prod.log | grep -i sitemap
```

#### Пошаговое решение

1. Запустить генерацию вручную (имя команды зависит от реализации, например, `app:sitemap:build` или `app:seo:sitemap`):

   ```bash
   php bin/console list | grep -i sitemap
   php bin/console <sitemap-build-command>
   ```

2. Если команды нет — sitemap отдаётся динамически контроллером, искать ошибку в `prod.log`.

#### Проверка

```bash
curl -I https://<domain>/sitemap.xml
curl -s https://<domain>/sitemap.xml | head
```

#### Профилактика

- systemd timer / cron на регулярную генерацию.
- Smoke test после deploy.

---

### 35. robots.txt неправильный

#### Симптомы

- `/robots.txt` показывает `Disallow: /` на production.
- Внезапное падение трафика.

#### Возможные причины

1. На production выкатили staging-конфиг (`staging` env обычно `Disallow: /`).
2. Изменения в шаблоне robots.

#### Быстрая диагностика

```bash
curl -s https://<domain>/robots.txt
grep -E 'APP_ENV|DOMAIN' /var/www/<project>/shared/.env.local
```

#### Пошаговое решение

1. Если robots генерируется по env — поправить `APP_ENV` / `SITE_URL` / domain configuration.
2. Если robots — статический файл — заменить корректным шаблоном из репозитория.
3. После исправления — `cache:clear && cache:warmup`.

#### Проверка

```bash
curl -s https://<domain>/robots.txt
```

#### Профилактика

- Smoke test `/robots.txt` после deploy.
- В CI отдельная проверка staging vs production.

---

### 36. Редиректы работают неправильно

#### Симптомы

- Циклы редиректов (`ERR_TOO_MANY_REDIRECTS`).
- Старые URL не ведут на новые.
- Вместо 301 — 302.

#### Возможные причины

1. Кривое правило в таблице/коде redirects.
2. nginx + Symfony делают редирект одновременно.
3. Trailing slash логика конфликтует.

#### Быстрая диагностика

```bash
curl -sIL https://<domain>/<old>
curl -sIL --max-redirs 5 https://<domain>/<old>
nginx -T 2>/dev/null | grep -E 'rewrite|return 30[12]'
php bin/console debug:router | grep -i redirect
```

#### Пошаговое решение

1. Найти источник редиректа (nginx vs Symfony).
2. Поправить правило (либо nginx vhost, либо запись в БД).
3. `cache:clear && cache:warmup`, `nginx -s reload`.

#### Проверка

```bash
curl -sIL https://<domain>/<old>
```

Ожидаемо: один 301 → конечный 200.

#### Профилактика

- Тесты редиректов в CI.
- Принцип «либо nginx, либо Symfony» — без дублирования.

---

### 37. SSL-сертификат истёк

#### Симптомы

- Браузер: `NET::ERR_CERT_DATE_INVALID`.
- `curl -I https://<domain>` → ошибка проверки сертификата.

#### Возможные причины

1. `certbot.timer` отключён / не работает.
2. DNS / порт 80 закрыт — Let’s Encrypt не может пройти challenge.
3. Превышен лимит выпуска (rate limit).

#### Быстрая диагностика

```bash
certbot certificates
echo | openssl s_client -servername <domain> -connect <domain>:443 2>/dev/null | openssl x509 -noout -dates
systemctl status certbot.timer --no-pager
journalctl -u certbot --no-pager -n 200
ss -ltnp | grep ':80'
```

#### Пошаговое решение

1. Проверить, что 80 порт открыт и nginx на нём слушает (`HTTP-01 challenge`).
2. Принудительно обновить:

   ```bash
   certbot renew --dry-run
   certbot renew
   systemctl reload nginx
   ```

3. Если rate limit — подождать, использовать staging endpoint Let’s Encrypt для тестов.

> **ВНИМАНИЕ.** Не выпускать сертификаты бесконтрольно через `--force-renewal` — можно упереться в недельный лимит.

#### Проверка

```bash
echo | openssl s_client -servername <domain> -connect <domain>:443 2>/dev/null | openssl x509 -noout -dates
curl -I https://<domain>
```

#### Профилактика

- `certbot.timer` enabled.
- Алерт «cert expires in < 14 days».

---

### 38. Certbot не обновляет сертификат

#### Симптомы

- `certbot renew --dry-run` падает.
- В логах — challenge failed.

#### Возможные причины

1. nginx vhost не отдаёт `.well-known/acme-challenge` корректно.
2. Изменился DNS.
3. Закрыт порт 80.

#### Быстрая диагностика

```bash
certbot certificates
journalctl -u certbot -n 200 --no-pager
nginx -T 2>/dev/null | grep -A 5 'acme-challenge'
curl -I http://<domain>/.well-known/acme-challenge/test
```

#### Пошаговое решение

1. Убедиться, что в HTTP vhost есть:

   ```nginx
   location ^~ /.well-known/acme-challenge/ {
       root /var/www/letsencrypt;
       default_type "text/plain";
   }
   ```

2. `certbot renew --dry-run` → исправлять до зелёного.

#### Проверка

```bash
certbot renew --dry-run
```

#### Профилактика

- Шаблон nginx-конфига с поддержкой challenge.
- Алерт на ошибку renew.

---

### 39. Домен смотрит не на тот сервер

#### Симптомы

- `dig +short <domain>` возвращает не тот IP.
- Сайт виден на старом IP.

#### Возможные причины

1. Не обновлены DNS-записи у регистратора.
2. Не истёк TTL.
3. Выбран не тот A/AAAA.

#### Быстрая диагностика

```bash
dig +short <domain> @8.8.8.8
dig +short <domain> @1.1.1.1
dig +short <domain> A
dig +short <domain> AAAA
```

#### Пошаговое решение

1. У регистратора — обновить A/AAAA на правильный IP.
2. Уменьшить TTL до 300 заранее перед миграцией.
3. Дождаться распространения DNS.

#### Проверка

```bash
dig +short <domain>
curl -I https://<domain>
```

#### Профилактика

- TTL 300 во время миграции, 3600+ в спокойное время.
- Документировать DNS-конфигурацию.

---

### 40. Deploy failed

#### Симптомы

- `tools/deploy/deploy-production.sh` упал.
- В CI / ssh-сессии — ошибка.

#### Возможные причины

1. Ошибка composer install / npm build (инциденты 44, 45).
2. Миграции упали (инцидент 14).
3. Нет места на диске (инцидент 46).
4. Permissions.
5. Health-check после переключения не прошёл.

#### Быстрая диагностика

```bash
tail -n 200 /var/www/<project>/shared/var/log/deploy.log    # если ведётся
ls -la /var/www/<project>/releases/ | tail
readlink /var/www/<project>/current
df -h
free -m
```

#### Пошаговое решение

1. Если переключение `current` ещё не произошло — старый релиз обслуживает трафик, можно спокойно разбираться.
2. Если переключение случилось и сайт сломан — откат (инцидент 42).
3. Починить причину, повторить deploy.

#### Проверка

```bash
curl -s https://<domain>/health
readlink /var/www/<project>/current
```

#### Профилактика

- Health-check после переключения с автоматическим rollback.
- См. [34-deployment](34-deployment.md), [35-cicd](35-cicd.md).

---

### 41. После deploy сайт сломался

#### Симптомы

- До deploy всё работало, после — 5xx, ошибки в `prod.log`, сломанные страницы.

#### Возможные причины

1. Бажный коммит.
2. Миграция применилась, но код не совместим.
3. Изменены env, не подхвачены.
4. Не сделан reload php-fpm (старый OPcache).

#### Быстрая диагностика

```bash
readlink /var/www/<project>/current
git -C /var/www/<project>/current log --oneline -n 5
tail -n 200 /var/www/<project>/current/var/log/prod.log
curl -I https://<domain>/health
```

#### Пошаговое решение

1. Если ошибка очевидна и это конфигурация — поправить и `cache:clear && cache:warmup && systemctl reload php8.5-fpm`.
2. Если непонятно — откат (инцидент 42).
3. Параллельно открыть инцидент / задачу, не пытаться «допиливать на проде».

#### Проверка

```bash
curl -I https://<domain>/
curl -s https://<domain>/health
tail -f /var/www/<project>/current/var/log/prod.log
```

#### Профилактика

- Staging-deploy + smoke перед production.
- Canary deploy / постепенный rollout (целевое).

---

### 42. Нужно быстро откатить релиз

#### Симптомы

- Сайт сломан после релиза.
- Решено откатиться.

#### Возможные причины

- Бажный релиз.
- Инцидентная обстановка.

#### Быстрая диагностика

```bash
readlink /var/www/<project>/current
ls -1t /var/www/<project>/releases | head -n 5
```

#### Пошаговое решение

1. Если используется `tools/deploy/rollback.sh`:

   ```bash
   HEALTH_URL=https://<domain>/health tools/deploy/rollback.sh
   ```

2. Ручной откат:

   ```bash
   PREV=$(ls -1t /var/www/<project>/releases | sed -n '2p')
   ln -sfn /var/www/<project>/releases/$PREV /var/www/<project>/current
   systemctl reload php8.5-fpm
   systemctl reload nginx
   systemctl restart <project>-messenger
   ```

3. Если используется git-pull deploy в одной директории:

   > **ВНИМАНИЕ.** Откат через git reset не возвращает миграции и assets. Действовать только при понимании последствий.

   ```bash
   cd /var/www/<project>
   git fetch --all
   git reset --hard <previous-sha>
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php bin/console cache:clear --env=prod --no-warmup
   php bin/console cache:warmup --env=prod
   systemctl reload php8.5-fpm
   ```

4. Если миграции уже применились и они **обратимо несовместимы** с предыдущим кодом — нужен restore из backup до миграции:

   > **ВНИМАНИЕ.** Откат БД из backup затрагивает все данные между backup’ом и текущим моментом. Это безвозвратно. См. [36-backup-restore](36-backup-restore.md). Сначала включить maintenance mode, остановить worker.

5. Если изменился `.env.local` — вернуть прошлую версию из copy/vault.

#### Проверка

```bash
readlink /var/www/<project>/current
curl -s https://<domain>/health
curl -I https://<domain>/
tail -f /var/www/<project>/current/var/log/prod.log
```

#### Профилактика

- Always-on `releases/` directory, retention ≥ 5.
- Backup до каждой миграции (это уже в deploy-скрипте).
- Skip-on-fail логика в скрипте, чтобы автоматический rollback срабатывал.

---

### 43. GitHub Actions / CI failed

#### Симптомы

- Pipeline в GitHub Actions красный.
- PR не мерджится.

#### Возможные причины

1. Тесты упали (phpunit / phpstan / php-cs-fixer / rector / vitest).
2. Линтеры (composer validate, eslint, stylelint).
3. Сборка фронта упала.
4. Деплой-runner недоступен.

#### Быстрая диагностика

- Открыть последний run в `Actions` на GitHub.
- Прочитать секцию failed step.

```bash
composer validate --strict
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpunit
npm ci && npm run build
```

#### Пошаговое решение

1. Воспроизвести локально.
2. Поправить.
3. Не отключать gates ради «быстро».

#### Проверка

- Pipeline зелёный.

#### Профилактика

- Pre-commit / pre-push хуки.
- См. [35-cicd](35-cicd.md), [38-coding-standards](38-coding-standards.md).

---

### 44. Composer install failed

#### Симптомы

- `composer install` падает.
- Deploy остановлен.

#### Возможные причины

1. Несовместимая версия PHP / расширений.
2. Битый `composer.lock`.
3. Нет доступа в packagist (сеть, прокси).
4. Auth для приватных репозиториев истёк.
5. Кончилось место.

#### Быстрая диагностика

```bash
php -v
php -m
composer --version
composer validate --strict
composer diagnose
df -h
```

#### Пошаговое решение

1. `composer install --no-dev --optimize-autoloader -v` — детальный лог.
2. Если PHP несовместим — обновить PHP / снизить требование.
3. Сетевые проблемы — проверить `https://repo.packagist.org/` доступен.
4. Если `composer.lock` поврежден — взять из git заново.

> **ВНИМАНИЕ.** Не делать `composer update` на production. Только `install` со существующим lockfile.

#### Проверка

```bash
composer install --no-dev --optimize-autoloader
ls -la vendor/autoload.php
```

#### Профилактика

- Lockfile — single source of truth.
- В CI — prefer-dist + кэш Composer.

---

### 45. npm install / npm build failed

См. также инцидент 24.

#### Симптомы

- `npm ci` падает.
- `npm run build` падает.

#### Быстрая диагностика

```bash
node -v
npm -v
df -h
free -m
ls -la package-lock.json
```

#### Пошаговое решение

1. На production — только `npm ci`. Если падает с «lockfile mismatch» — пересобрать lockfile в dev/CI, пересоздать релиз.
2. Если OOM — `NODE_OPTIONS=--max-old-space-size=2048`.
3. Если деп нестабилен — pin версию.

#### Проверка

```bash
npm ci
npm run build
ls -la public_html/build/
```

#### Профилактика

- Сборка фронта в CI, артефакт на сервер (см. [22-frontend-assets](22-frontend-assets.md)).

---

### 46. Disk full

#### Симптомы

- `df -h` показывает 100% или > 95%.
- Любые операции записи падают.
- PostgreSQL переходит в read-only.

#### Возможные причины

1. Логи разрослись (см. инцидент 56).
2. Старые backup’ы не удаляются (см. инцидент 55).
3. Старые `releases/` накопились.
4. `var/cache` в множестве релизов.
5. PG WAL не ротирует.

#### Быстрая диагностика

```bash
df -h
du -sh /var/www/<project>/releases/* | sort -h | tail
du -sh /var/www/<project>/shared/backups/*
du -sh /var/log/* | sort -h | tail
du -sh /var/lib/postgresql/* | sort -h | tail
du -sh /var/www/<project>/shared/public_html/uploads
```

#### Пошаговое решение

1. Удалить старые релизы (оставив последние 5):

   ```bash
   ls -1t /var/www/<project>/releases | tail -n +6 | while read r; do
     rm -rf /var/www/<project>/releases/$r
   done
   ```

   > **ВНИМАНИЕ.** Не удалять текущий и предыдущий релиз (он нужен для rollback).

2. Удалить старые backup’ы по retention (см. [36-backup-restore](36-backup-restore.md)).
3. Запустить logrotate (инцидент 56).
4. `apt clean`, `journalctl --vacuum-time=14d`.
5. Если PG в read-only — освободить место и `systemctl restart postgresql`.

#### Проверка

```bash
df -h
du -sh /var/www/<project>/releases/
```

#### Профилактика

- Алерт на disk > 80% / > 90%.
- Cleanup старых релизов / бэкапов в deploy-скрипте.
- Logrotate.

---

### 47. RAM закончилась / OOM killer

#### Симптомы

- Случайные падения процессов.
- В `dmesg` / `syslog`: `Out of memory: Killed process ... php-fpm/postgres/redis`.

#### Быстрая диагностика

```bash
free -m
dmesg | grep -i 'killed process'
grep -i 'killed process' /var/log/syslog
ps aux --sort=-%mem | head
top
```

#### Пошаговое решение

1. Найти процесса-виновника. Часто — PHP-FPM с большим `pm.max_children`.
2. Уменьшить `pm.max_children` или PHP `memory_limit`.
3. Добавить swap (временно):

   ```bash
   fallocate -l 2G /swapfile
   chmod 600 /swapfile
   mkswap /swapfile
   swapon /swapfile
   ```

4. Перезапустить упавшие сервисы.

#### Проверка

```bash
free -m
systemctl status php8.5-fpm postgresql redis-server <project>-messenger --no-pager
```

#### Профилактика

- Алерт на free RAM < 15%.
- Sizing FPM workers под фактическую RAM.
- Включён swap (минимально, как страховка).

---

### 48. CPU load высокий

#### Симптомы

- `uptime` показывает load >> числа CPU.
- Сайт тормозит.

#### Быстрая диагностика

```bash
uptime
top
htop
ps aux --sort=-%cpu | head
sudo -u postgres psql -c "SELECT pid, state, query FROM pg_stat_activity WHERE state <> 'idle' ORDER BY query_start;"
```

#### Пошаговое решение

1. Найти процесса-виновника.
2. Если PostgreSQL — слать `EXPLAIN`, смотреть медленные запросы (инцидент 50).
3. Если PHP-FPM — найти долгий запрос (slow log), профилировать.
4. Если Redis — `redis-cli --latency`, `redis-cli slowlog get`.

#### Проверка

```bash
uptime
top
```

#### Профилактика

- Slow log в FPM, в PG (`log_min_duration_statement`).
- Метрики CPU / load в мониторинге.

---

### 49. Сайт работает медленно

#### Симптомы

- TTFB > 1с, страницы грузятся медленно.
- Алерт от мониторинга.

#### Возможные причины

1. Медленный SQL.
2. Кэш не работает (Redis недоступен).
3. Нет OPcache / cache:warmup.
4. CPU/RAM (инциденты 47, 48).
5. Внешние API без таймаутов.

#### Быстрая диагностика

```bash
curl -o /dev/null -s -w 'http_code=%{http_code} ttfb=%{time_starttransfer}s total=%{time_total}s\n' https://<domain>/
redis-cli ping
tail -n 200 /var/www/<project>/current/var/log/prod.log
sudo -u postgres psql -c "SELECT pid, query, state, now()-query_start AS dur FROM pg_stat_activity WHERE state <> 'idle' ORDER BY dur DESC LIMIT 10;"
```

#### Пошаговое решение

1. Проверить cache (инцидент 17), Redis (16), DB (50).
2. Прогреть Symfony cache: `cache:clear && cache:warmup`.
3. Проверить OPcache: `php -i | grep opcache.enable`.

#### Проверка

```bash
curl -o /dev/null -s -w 'ttfb=%{time_starttransfer}s\n' https://<domain>/
```

#### Профилактика

- Метрики latency.
- Slow log.

---

### 50. Медленные SQL-запросы

#### Симптомы

- В PG логе — `duration: ... ms statement: ...`.
- Сайт тормозит.

#### Быстрая диагностика

```bash
sudo -u postgres psql -d <db_name> -c "SELECT pid, now()-query_start AS dur, state, query FROM pg_stat_activity WHERE state <> 'idle' ORDER BY dur DESC LIMIT 20;"
sudo -u postgres psql -d <db_name> -c "SELECT * FROM pg_stat_statements ORDER BY total_exec_time DESC LIMIT 20;"
tail -n 500 /var/log/postgresql/postgresql-18-main.log | grep -i duration
```

#### Пошаговое решение

1. Снять `EXPLAIN (ANALYZE, BUFFERS)` для подозрительного запроса.
2. Создать индекс (через миграцию, в production — `CREATE INDEX CONCURRENTLY`).

   > **ВНИМАНИЕ.** Не запускать `CREATE INDEX` на больших таблицах в часы пик без `CONCURRENTLY`.

3. Если виноват N+1 в коде — фиксить в коде, релиз.

#### Проверка

- Запрос быстрее, среднее время в `pg_stat_statements` упало.

#### Профилактика

- Включён `pg_stat_statements`.
- `log_min_duration_statement = 500ms` (или подобное).
- Code review с обращением внимания на N+1.

---

### 51. Проблемы с backup

#### Симптомы

- Алерт «backup older than 24h».
- В `shared/backups/db/` нет свежих файлов.

#### Возможные причины

1. Cron / timer не запустился.
2. `pg_dump` упал.
3. Кончилось место.

#### Быстрая диагностика

```bash
ls -lat /var/www/<project>/shared/backups/db/ | head
systemctl list-timers --no-pager | grep -i backup
journalctl -u <backup-timer-or-service> -n 200 --no-pager
df -h
```

#### Пошаговое решение

1. Запустить backup вручную:

   ```bash
   PGPASSWORD=... pg_dump -h 127.0.0.1 -U <user> -d <db_name> --format=custom --file=/var/www/<project>/shared/backups/db/<db_name>-manual-$(date +%Y%m%d-%H%M%S).dump
   ```

2. Починить timer/cron, проверить путь и права.

#### Проверка

```bash
ls -lat /var/www/<project>/shared/backups/db/ | head
pg_restore --list /var/www/<project>/shared/backups/db/<file>.dump | head
```

#### Профилактика

- Алерт на «нет нового backup за 26h».
- Регулярный smoke-restore (раз в неделю).

---

### 52. Backup не создаётся

См. инцидент 51 + проверить:

- Права user’а БД на `pg_dump`.
- Свободное место на диске.
- Корректный пароль (`PGPASSWORD` или `~/.pgpass`).
- Доступность хоста PG.

#### Быстрая диагностика

```bash
PGPASSWORD=... psql -h 127.0.0.1 -U <user> -d <db_name> -c "SELECT 1;"
df -h
ls -ld /var/www/<project>/shared/backups/db/
```

#### Пошаговое решение

1. Запустить вручную, прочитать stderr.
2. Поправить причину.
3. Проверить, что `tools/deploy/...` или backup-скрипт идемпотентен.

---

### 53. Backup не загружается во внешнее хранилище

#### Симптомы

- Локальный backup есть, off-site копии — нет.

#### Быстрая диагностика

```bash
ls -lat /var/www/<project>/shared/backups/db/ | head
journalctl -u <offsite-sync-service> -n 200 --no-pager
# rclone config:
rclone listremotes
rclone ls <remote>:<bucket>/db/ | head
```

#### Пошаговое решение

1. Запустить sync вручную.
2. Проверить креды доступа к S3 / Object Storage.
3. Проверить network / firewall.
4. Шифрование backup’а перед upload (см. [36-backup-restore](36-backup-restore.md)).

#### Проверка

- В удалённом хранилище есть свежий объект.

#### Профилактика

- Алерт на «no off-site copy newer than 26h».

---

### 54. Restore из backup не проходит

#### Симптомы

- `pg_restore` падает.
- Восстановленная БД пустая или с ошибками.

#### Возможные причины

1. Битый файл backup.
2. Несовместимость версий PG.
3. Не та БД / не тот user.
4. FK не создаются из-за `--no-owner` без правильной схемы.

#### Быстрая диагностика

```bash
pg_restore --list /var/www/<project>/shared/backups/db/<file>.dump | head
file /var/www/<project>/shared/backups/db/<file>.dump
sudo -u postgres psql -c "SELECT version();"
```

#### Пошаговое решение

1. Проверить, что бэкап целый (`--list` показывает таблицы).
2. Восстанавливать в **отдельную** БД, не поверх production:

   ```bash
   createdb -h 127.0.0.1 -U <user> <db_name>_restored
   pg_restore -h 127.0.0.1 -U <user> --dbname=<db_name>_restored --no-owner --no-privileges /var/www/<project>/shared/backups/db/<file>.dump
   ```

3. Затем — переключение приложения через смену `DATABASE_URL` / переименование БД.

> **ВНИМАНИЕ.** Не восстанавливать поверх production-БД без явного решения и резерва текущего состояния.

#### Проверка

- В восстановленной БД таблицы и счётчики на месте.
- Smoke-test приложения с `DATABASE_URL` указанным на restored.

#### Профилактика

- Регулярный smoke-restore (минимум раз в неделю на staging-like окружении).
- См. [36-backup-restore](36-backup-restore.md).

---

### 55. Cleanup не удаляет старые файлы

#### Симптомы

- Старые `releases/`, backup’ы, кэш не убираются.
- Диск растёт.

#### Возможные причины

1. Скрипт cleanup не настроен.
2. Permissions.
3. Bug в скрипте.

#### Быстрая диагностика

```bash
systemctl list-timers --no-pager
ls -1t /var/www/<project>/releases | wc -l
ls -lat /var/www/<project>/shared/backups/db/ | tail
```

#### Пошаговое решение

1. Запустить cleanup вручную (скрипт проекта или просто rm с явными путями):

   > **ВНИМАНИЕ.** Удалять можно только из `releases/` (не текущий и не предыдущий) и из `backups/` старее retention. Никогда — `current`, `shared/.env.local`, `shared/public_html/uploads`.

2. Починить cron / timer.

#### Проверка

```bash
df -h
ls -1t /var/www/<project>/releases | wc -l
```

#### Профилактика

- Cleanup в deploy-скрипте (оставлять последние N релизов).
- Logrotate (инцидент 56).

---

### 56. Log files слишком большие

#### Симптомы

- `var/log/prod.log` десятки гигабайт.
- nginx access.log заполняет диск.

#### Возможные причины

1. Logrotate не настроен / упал.
2. Слишком verbose уровень логирования.
3. Дебаг-логирование оставлено включённым.

#### Быстрая диагностика

```bash
ls -lah /var/www/<project>/shared/var/log/
ls -lah /var/log/nginx/
cat /etc/logrotate.d/nginx
cat /etc/logrotate.d/<project>            # если есть
logrotate -d /etc/logrotate.conf | head -n 50
```

#### Пошаговое решение

1. Принудительная ротация:

   ```bash
   logrotate -f /etc/logrotate.d/nginx
   logrotate -f /etc/logrotate.d/<project>
   ```

2. Если файла logrotate для приложения нет — создать (см. [28-logging-observability](28-logging-observability.md)).
3. Проверить уровень логирования (`monolog.yaml`), убрать DEBUG в prod.

> **ВНИМАНИЕ.** Не удалять `prod.log` через `rm` — открытые file descriptor’ы продолжат писать в удалённый inode, место не освободится. Использовать `: > prod.log` или logrotate.

#### Проверка

```bash
ls -lah /var/www/<project>/shared/var/log/
df -h
```

#### Профилактика

- Logrotate с `daily`, `rotate 14`, `compress`, `copytruncate` или `postrotate kill -USR1`.

---

### 57. Healthcheck endpoint показывает ошибку

#### Симптомы

- `GET /health` возвращает 5xx или JSON с `ok: false`.

#### Возможные причины

1. БД недоступна (см. инцидент 11/12).
2. Redis недоступен (16).
3. Миграции не применены.
4. `var/cache` / `var/log` не writable.

#### Быстрая диагностика

```bash
curl -s https://<domain>/health
curl -s https://<domain>/health/ready    # если разделено
php bin/console doctrine:migrations:status
redis-cli ping
ls -ld /var/www/<project>/current/var/cache /var/www/<project>/current/var/log
```

#### Пошаговое решение

- В зависимости от того, что упало в `health` JSON — переходить к соответствующему инциденту.

#### Проверка

```bash
curl -s https://<domain>/health
```

#### Профилактика

- См. [29-healthchecks](29-healthchecks.md).

---

### 58. Cron / systemd timer не запускается

#### Симптомы

- Запланированная задача не выполняется (sitemap не обновляется, backup не создаётся, cleanup не идёт).

#### Возможные причины

1. Timer/cron disabled.
2. Юнит сломан.
3. Задача висит в одиночном instance’е.

#### Быстрая диагностика

```bash
systemctl list-timers --no-pager
systemctl status <name>.timer --no-pager
journalctl -u <name>.service -n 200 --no-pager
crontab -l -u <user>
ls /etc/cron.d/
```

#### Пошаговое решение

1. `systemctl enable --now <name>.timer`.
2. Запустить вручную: `systemctl start <name>.service`, посмотреть журнал.

#### Проверка

```bash
systemctl list-timers --no-pager | grep <name>
journalctl -u <name>.service -n 50 --no-pager
```

#### Профилактика

- Алерт «timer last run > expected interval».

---

### 59. Почта / Mailer не отправляет письма

#### Симптомы

- В админке формы отправились, письма не пришли.
- В `prod.log`: `Mailer\Exception\TransportException`.

#### Возможные причины

1. Неверный `MAILER_DSN`.
2. SMTP-провайдер заблокировал / лимит.
3. Worker messenger не запущен (если письма уходят асинхронно).
4. Reverse DNS / SPF/DKIM/DMARC не настроены — письма попадают в спам.

#### Быстрая диагностика

```bash
grep MAILER_DSN /var/www/<project>/shared/.env.local
php bin/console messenger:stats
journalctl -u <project>-messenger -n 200 --no-pager
tail -n 200 /var/www/<project>/current/var/log/prod.log | grep -iE 'mail|smtp'
swaks --to test@example.com --server <smtp_host> --port 587 --tls --auth LOGIN --auth-user <user>
```

#### Пошаговое решение

1. Проверить креды/DSN.
2. Запустить worker (инцидент 19).
3. Если письма помечаются спамом — настроить SPF/DKIM/DMARC, reverse DNS.

#### Проверка

- Тестовая отправка проходит, в `prod.log` нет ошибок mailer.

#### Профилактика

- Мониторинг bounce rate.
- См. [11-infrastructure-layer](11-infrastructure-layer.md).

---

### 60. API отдаёт ошибки

#### Симптомы

- `/api/...` возвращает 4xx/5xx.
- В `prod.log`: исключения уровня API.

#### Возможные причины

1. Изменилась схема DTO без миграции клиента.
2. Изменилась авторизация (token / scope).
3. CORS / rate limiting срабатывает.

#### Быстрая диагностика

```bash
curl -i https://<domain>/api/<endpoint>
curl -i -H 'Authorization: Bearer <token>' https://<domain>/api/<endpoint>
tail -n 200 /var/www/<project>/current/var/log/prod.log | grep -i api
php bin/console debug:router | grep api
```

#### Пошаговое решение

1. По stacktrace — соответствующий инцидент.
2. См. [14-api-area](14-api-area.md), [30-error-handling](30-error-handling.md).

#### Проверка

```bash
curl -i https://<domain>/api/<endpoint>
```

#### Профилактика

- Контрактные тесты API в CI.

---

### 61. Dev-инструменты случайно доступны в production

#### Симптомы

- `/_profiler`, `/_wdt`, `/dev/...` отдают 200.
- В Search Console — индексируется dev-страница.

#### Возможные причины

1. `APP_ENV=dev` / `APP_DEBUG=1` (см. инцидент 9).
2. Dev-роуты не закрыты в production (`when@dev`).
3. Vite dev server запущен на проде.

#### Быстрая диагностика

```bash
curl -I https://<domain>/_profiler
curl -I https://<domain>/_wdt/abc
ps aux | grep -E 'vite|webpack-dev'
grep -E 'APP_(ENV|DEBUG)' /var/www/<project>/shared/.env.local
```

#### Пошаговое решение

1. Принудительно `APP_ENV=prod`, `APP_DEBUG=0`.
2. Закрыть dev-роуты в nginx:

   ```nginx
   location ~ ^/(_(profiler|wdt|fragment)|dev) {
       return 404;
   }
   ```

3. Убить vite dev server, если запущен.
4. Перевыпустить `APP_SECRET` если был раскрыт.

> **ВНИМАНИЕ.** Если профайлер был доступен публично — считать секреты скомпрометированными. Ротация всех секретов.

#### Проверка

```bash
curl -I https://<domain>/_profiler
curl -I https://<domain>/_wdt/abc
```

Ожидаемо: 404.

#### Профилактика

- В nginx — явный deny на dev-роуты.
- В CI smoke-тесте production — проверка `_profiler` 404.
- См. [15-dev-area](15-dev-area.md).

---

### 62. Нужно перевести сайт в maintenance mode

#### Симптомы

- Плановые работы / restore / опасная миграция.
- Нужно вернуть пользователям читабельную страницу.

#### Возможные причины

- Решение оператора.

#### Быстрая диагностика

```bash
ls /var/www/<project>/shared/maintenance.flag 2>/dev/null
nginx -T 2>/dev/null | grep -i maintenance
```

#### Пошаговое решение

Если доступен штатный механизм приложения, использовать его в первую очередь:

```bash
php bin/console app:maintenance:on --message="Сайт временно недоступен" --allow-ip=127.0.0.1
php bin/console app:maintenance:status
```

> Если в проекте уже есть собственный механизм maintenance mode — использовать его.
> Ниже — **шаблонный generic-вариант** на nginx + файл-флаг. Адаптировать под свой vhost.

1. Положить файл-флаг:

   ```bash
   touch /var/www/<project>/shared/maintenance.flag
   ```

2. В nginx-vhost добавить (если ещё не добавлено):

   ```nginx
   set $maintenance 0;
   if (-f /var/www/<project>/shared/maintenance.flag) { set $maintenance 1; }
   if ($maintenance) {
       return 503;
   }
   error_page 503 /maintenance.html;
   location = /maintenance.html {
       root /var/www/<project>/shared/public_html;
       internal;
   }
   ```

3. `nginx -t && systemctl reload nginx`.
4. Опционально — остановить worker, чтобы не обрабатывал сообщения во время работ:

   ```bash
   systemctl stop <project>-messenger
   ```

#### Проверка

```bash
curl -I https://<domain>/
```

Ожидаемо: `HTTP/2 503` + `maintenance.html` для GET.

#### Профилактика

- Maintenance mode — стандартный шаг в runbook опасных операций (миграции, restore).

---

### 63. Нужно снять maintenance mode

#### Пошаговое решение

Если maintenance был включён штатной командой:

```bash
php bin/console app:maintenance:off
```

1. Удалить флаг:

   ```bash
   rm -f /var/www/<project>/shared/maintenance.flag
   ```

2. (Если меняли nginx) `nginx -t && systemctl reload nginx`.
3. Запустить worker:

   ```bash
   systemctl start <project>-messenger
   ```

#### Проверка

```bash
curl -I https://<domain>/
curl -s https://<domain>/health
systemctl status <project>-messenger --no-pager
```

#### Профилактика

- Чек-лист «после maintenance»: проверить health, smoke-тесты, очередь, логи.

---

### 64. Нужно собрать минимальный отчёт об инциденте

См. также раздел **9. Incident report (шаблон)** ниже.

#### Что собирать

1. Время начала и конца инцидента.
2. Симптомы и impact (какие user journey пострадали, на сколько процентов трафика).
3. Действия, которые выполнили (по шагам, со временем).
4. Что помогло, что не помогло.
5. Корневую причину (root cause).
6. Follow-up задачи.

#### Команды для снятия артефактов

```bash
date
hostname
uptime
df -h
free -m
systemctl status nginx php8.5-fpm postgresql redis-server <project>-messenger --no-pager
readlink /var/www/<project>/current
git -C /var/www/<project>/current log --oneline -n 10
tail -n 500 /var/www/<project>/current/var/log/prod.log > /tmp/incident-prod.log
tail -n 500 /var/log/nginx/error.log > /tmp/incident-nginx-error.log
journalctl --since '-2h' --no-pager > /tmp/incident-journal.log
```

Файлы из `/tmp/incident-*.log` приложить к incident report.

---

## 7. Emergency checklist

5–15 минут с момента «что-то не так». Идти строго по порядку.

1. **Проверить доступность снаружи.**

   ```bash
   curl -I --max-time 10 https://<domain>
   curl -s --max-time 10 https://<domain>/health
   ```

2. **Зайти на сервер.**

   ```bash
   ssh <user>@<host>
   pwd; whoami; hostname; date; uptime
   ```

3. **Проверить nginx.**

   ```bash
   systemctl status nginx --no-pager
   nginx -t
   tail -n 50 /var/log/nginx/error.log
   ```

4. **Проверить PHP-FPM.**

   ```bash
   systemctl status php8.5-fpm --no-pager
   tail -n 50 /var/log/php8.5-fpm.log
   ```

5. **Проверить PostgreSQL.**

   ```bash
   systemctl status postgresql --no-pager
   sudo -u postgres psql -c "SELECT 1;"
   ```

6. **Проверить Redis.**

   ```bash
   systemctl status redis-server --no-pager
   redis-cli ping
   ```

7. **Проверить ресурсы.**

   ```bash
   df -h
   free -m
   uptime
   ps aux --sort=-%mem | head
   ps aux --sort=-%cpu | head
   ```

8. **Проверить prod.log.**

   ```bash
   tail -n 100 /var/www/<project>/current/var/log/prod.log
   ```

9. **Проверить nginx error.log.**

   ```bash
   tail -n 100 /var/log/nginx/error.log
   ```

10. **Проверить последний deploy.**

    ```bash
    readlink /var/www/<project>/current
    ls -1t /var/www/<project>/releases | head
    ```

11. **При необходимости — maintenance mode** (инцидент 62).
12. **При необходимости — rollback** (инцидент 42).
13. **Зафиксировать инцидент** (раздел 9, шаблон ниже).

---

## 8. Quick commands reference

### System

```bash
pwd
whoami
hostname
date
uptime
df -h
du -sh /var/www/<project>/current/var/* /var/www/<project>/shared/public_html/uploads/*
free -m
top
htop
ps aux --sort=-%mem | head
ps aux --sort=-%cpu | head
ss -ltnp
```

### Nginx

```bash
systemctl status nginx --no-pager
systemctl reload nginx
systemctl restart nginx
nginx -t
nginx -T 2>/dev/null | less
journalctl -u nginx -n 100 --no-pager
tail -n 100 /var/log/nginx/access.log
tail -n 100 /var/log/nginx/error.log
```

### PHP-FPM

```bash
systemctl status php8.5-fpm --no-pager
systemctl reload php8.5-fpm
systemctl restart php8.5-fpm
php -v
php -m
php -i | grep -E 'opcache|memory_limit|upload_max_filesize|post_max_size'
journalctl -u php8.5-fpm -n 100 --no-pager
tail -n 100 /var/log/php8.5-fpm.log
```

### Symfony

```bash
cd /var/www/<project>/current
php bin/console about
php bin/console debug:router
php bin/console debug:container
php bin/console debug:dotenv
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:query:sql "SELECT 1"
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
php bin/console cache:pool:list
php bin/console cache:pool:clear cache.app
```

### PostgreSQL

```bash
systemctl status postgresql --no-pager
psql --version
sudo -u postgres psql -c "SELECT version();"
sudo -u postgres psql -c "\l"
sudo -u postgres psql -c "\du"
sudo -u postgres psql -d <db_name> -c "SELECT count(*) FROM pg_stat_activity;"
sudo -u postgres psql -d <db_name> -c "SELECT pid, state, query FROM pg_stat_activity WHERE state <> 'idle' ORDER BY query_start;"
```

### Redis

```bash
systemctl status redis-server --no-pager
redis-cli ping
redis-cli info
redis-cli info memory
redis-cli info clients
redis-cli slowlog get 20
redis-cli --latency
```

### Messenger

```bash
php bin/console messenger:stats
php bin/console messenger:failed:show
php bin/console messenger:failed:show <id>
php bin/console messenger:failed:retry --force
php bin/console messenger:failed:remove <id>
php bin/console messenger:stop-workers
systemctl status <project>-messenger --no-pager
systemctl restart <project>-messenger
journalctl -u <project>-messenger -n 200 --no-pager
```

### Deploy

```bash
readlink /var/www/<project>/current
ls -1t /var/www/<project>/releases | head
git -C /var/www/<project>/current log --oneline -n 10
composer validate --strict
composer install --no-dev --optimize-autoloader
npm ci
npm run build
HEALTH_URL=https://<domain>/health tools/deploy/rollback.sh
```

### Logs

```bash
tail -n 100 /var/www/<project>/current/var/log/prod.log
tail -f    /var/www/<project>/current/var/log/prod.log
grep -E 'ERROR|CRITICAL' /var/www/<project>/current/var/log/prod.log | tail -n 100
journalctl -xe --no-pager
journalctl -u nginx -n 100 --no-pager
journalctl -u php8.5-fpm -n 100 --no-pager
```

### Disk / RAM / CPU

```bash
df -h
du -sh /var/www/<project>/releases/*
du -sh /var/www/<project>/shared/backups/*
free -m
top
htop
ps aux --sort=-%mem | head
ps aux --sort=-%cpu | head
dmesg | tail -n 100
```

### SSL

```bash
certbot certificates
certbot renew --dry-run
certbot renew
echo | openssl s_client -servername <domain> -connect <domain>:443 2>/dev/null | openssl x509 -noout -dates
systemctl status certbot.timer --no-pager
```

### Backup / Restore

```bash
ls -lat /var/www/<project>/shared/backups/db/ | head
PGPASSWORD=... pg_dump -h 127.0.0.1 -U <user> -d <db_name> --format=custom --file=/var/www/<project>/shared/backups/db/<db_name>-$(date +%Y%m%d-%H%M%S).dump
pg_restore --list /var/www/<project>/shared/backups/db/<file>.dump | head
createdb -h 127.0.0.1 -U <user> <db_name>_restored
pg_restore -h 127.0.0.1 -U <user> --dbname=<db_name>_restored --no-owner --no-privileges /var/www/<project>/shared/backups/db/<file>.dump
rsync -av /var/www/<project>/shared/backups/uploads/<date>/ /var/www/<project>/shared/public_html/uploads/
```

### Permissions

```bash
chown -R www-data:www-data /var/www/<project>/current/var
chown -R www-data:www-data /var/www/<project>/current/public_html/build
chown -R www-data:www-data /var/www/<project>/shared/public_html/uploads
chown -R www-data:www-data /var/www/<project>/shared/var/log
chmod 640 /var/www/<project>/shared/.env.local
sudo -u www-data test -w /var/www/<project>/current/var/cache && echo OK || echo NO_WRITE
```

### Frontend assets

```bash
ls -la /var/www/<project>/current/public_html/build/
test -f /var/www/<project>/current/public_html/build/manifest.json && echo OK || echo MISSING
node -v
npm -v
npm ci
npm run build
```

---

## 9. Incident report (шаблон)

Минимальный отчёт об инциденте. Заполнять во время или сразу после.

```text
INCIDENT-<YYYYMMDD>-<NN>

Дата и время начала:        <YYYY-MM-DD HH:MM TZ>
Дата и время восстановления: <YYYY-MM-DD HH:MM TZ>
Длительность:               <Xч Yм>
Кто обнаружил:              <имя / алерт / пользователь>
Кто реагировал:              <дежурный / on-call>

Симптомы:
- <что было видно пользователям>
- <что было видно в логах>
- <какие алерты сработали>

Impact:
- Затронутые user journeys: <...>
- Доля трафика / клиентов: <...>
- Потерянные данные: <да/нет, какие>

Хронология действий (UTC):
- HH:MM — <что сделали>
- HH:MM — <что сделали>
- ...

Что помогло:
- <конкретные шаги>

Что не помогло:
- <шаги, которые не дали результата>

Корневая причина (root cause):
- <технически точно>

Что предотвратит повторение:
- <конкретные изменения>

Follow-up задачи:
- [ ] <тикет / PR / TODO>
- [ ] ...

Ссылки:
- Алерты: <...>
- Логи (артефакты): <...>
- PR / коммит rollback’а: <...>
```

---

## 10. Escalation: когда нужно остановиться

В этих ситуациях **не продолжать действия в одиночку**. Эскалировать (ответственный руководитель / DBA / security), фиксировать состояние, не торопиться:

- Есть риск **потери данных** (миграция применилась частично, нет понятного отката).
- **Backup отсутствует** или не верифицирован, а действия деструктивные.
- **Непонятно, какая БД production**, а нужно делать что-то опасное.
- Уже **удалены** файлы uploads / содержимое БД / релиз `current`.
- Приложение пишет в **неправильное окружение** (prod пишет в staging БД или наоборот).
- Есть признаки **взлома**: посторонние процессы, unknown SSH-ключи, странные cron job, исходящие сетевые соединения, неожиданные изменения в файлах вне deploy.
- Сертификаты / секреты потенциально **скомпрометированы** (например, был включён `APP_DEBUG=true` в публичной сети).
- Несколько критичных сервисов одновременно лежат и причина непонятна — собрать команду, не «починять» вслепую.

В таких случаях:

1. Зафиксировать текущее состояние: `pg_dump`, `tar` критичных директорий, `journalctl` за период, `iptables-save`, список процессов.
2. Перевести сайт в maintenance mode (инцидент 62).
3. Эскалировать.
4. Не запускать новые миграции / restore / deploy до решения.

---

## 11. Замечание о синхронизации с остальной документацией

- Имена сервисов (`php8.5-fpm`, `<project>-messenger`), пути (`/var/www/<project>`), команды deploy и rollback должны соответствовать [34-deployment](34-deployment.md), [35-cicd](35-cicd.md), [36-backup-restore](36-backup-restore.md).
- Если в этих документах изменилась схема release layout или имя systemd unit — обновить и здесь.
- При появлении новых runbook’ов (например, для Telegram alerts, Sentry, новых API) — добавлять разделы по той же шестичастной структуре.
- Для AI-агента в Cursor: при работе по этому runbook соблюдать правила [39-agent-guide](39-agent-guide.md) и [40-cursor-rules](40-cursor-rules.md). На production-системе никаких операций без явного подтверждения оператора.
