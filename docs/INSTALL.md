# Установка

## Требования

- PHP `>=8.4`
- PHP extensions: `ctype`, `iconv`, `intl`, `mbstring`, `pdo_pgsql`, `redis`
- Composer 2
- Node.js `>=22`
- PostgreSQL `>=18`
- Redis
- Nginx или локальный web server OSPanel

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
