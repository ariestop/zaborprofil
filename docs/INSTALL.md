# Установка

Основной способ локальной разработки — Docker Compose. Native установка нужна для MAMP/OSPanel или диагностики production-like окружения без контейнеров.

## Docker

```bash
cp .env.local.example .env.local
make build
make up
make composer-install
make npm-install
make migrate
make npm-build
```

Подробно: `docs/LOCAL_DOCKER.md`.

## Native требования

Минимальные зафиксированные версии:

- PHP `>=8.5`
- PHP extensions: `ctype`, `iconv`, `intl`, `mbstring`, `pdo_pgsql`, `redis`
- Composer 2
- Node.js `>=25.9.0`
- npm `>=11.12.1` (поставляется с Node.js 25.9.0)
- nginx `>=1.30.0` (или локальный web server OSPanel совместимой версии)
- PostgreSQL `>=18`
- Redis `>=8`

Версии в Docker (`docker/php/Dockerfile`, `docker/node/Dockerfile`, `docker-compose.yml`) запиннены к этим минимумам: `php:8.5-fpm-bookworm` (плавающий patch внутри 8.5.x), `node:25.9.0-bookworm` и `nginx:1.30.0-alpine`. Все три образа доступны в Docker Hub для linux/amd64 и linux/arm64.

## Шаги

1. Установить PHP-зависимости:

```bash
composer install
```

2. Создать локальный `.env.local`:

```dotenv
APP_ENV=dev
APP_SECRET=change-this-secret
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/zaborprofil?serverVersion=18&charset=utf8"
REDIS_URL="redis://127.0.0.1:6379"
```

3. Применить миграции:

```bash
php bin/console doctrine:migrations:migrate
```

4. Установить frontend-зависимости и собрать assets:

```bash
npm install
npm run build
```

`npm install` обязателен до открытия `tailwind.config.ts` в IDE и до запуска `npm run build`/`tsc --noEmit`. Конфиг Tailwind импортирует плагин `@tailwindcss/typography`, объявленный в `package.json` как devDependency. Без `node_modules/` TypeScript будет падать с ошибкой `Cannot find module '@tailwindcss/typography'`.

5. Проверить приложение:

```bash
php bin/console about
php bin/console router:match /health
```

## OSPanel

В `.osp/project.ini` web root должен оставаться:

```ini
web_root = {base_dir}/public_html
```

## Troubleshooting

### `Cannot find module '@tailwindcss/typography'` в `tailwind.config.ts`

Причина: не установлены npm-зависимости (нет `node_modules/`) или в системе отсутствует Node.js.

Решение:

```bash
node -v   # должно быть >= 22, иначе сначала: brew install node
npm install
```

После установки в Cursor/VS Code перезапустить TS-сервер: `Cmd+Shift+P` → `TypeScript: Restart TS Server`.

Менять сам `tailwind.config.ts` не нужно — импорт `@tailwindcss/typography` корректен и соответствует объявленной devDependency в `package.json`.
