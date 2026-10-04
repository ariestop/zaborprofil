#!/usr/bin/env bash
#
# Тесты tools/deploy/deploy-beget.sh без сети, Composer и реального приложения:
# локальный bare-репозиторий вместо GitHub и фейковый PHP_BIN, который пишет вызовы composer/console.
# Реальный php нужен только для проверки версии/расширений и генерации bcrypt-хеша.
#
# Запуск: bash tests/shell/deploy-beget.sh

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DEPLOY_SCRIPT="$ROOT_DIR/tools/deploy/deploy-beget.sh"
REAL_PHP="$(command -v php || true)"

[[ -n "$REAL_PHP" ]] || { echo "Нужен php в PATH (для проверки версии и password_hash)." >&2; exit 2; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0

ok() { PASS=$((PASS + 1)); printf 'ok   - %s\n' "$1"; }
ko() { FAIL=$((FAIL + 1)); printf 'FAIL - %s\n' "$1"; }

assert_eq() {
  if [[ "$2" == "$3" ]]; then ok "$1"; else ko "$1 (ожидалось '$3', получено '$2')"; fi
}

assert_contains() {
  if grep -qF -- "$3" <<<"$2"; then ok "$1"; else ko "$1 (нет '$3' в выводе)"; fi
}

assert_file_contains() {
  if [[ -f "$2" ]] && grep -qF -- "$3" "$2"; then ok "$1"; else ko "$1 (нет '$3' в $2)"; fi
}

assert_not_exists() {
  if [[ ! -e "$2" ]]; then ok "$1"; else ko "$1 ($2 существует)"; fi
}

HASH="$("$REAL_PHP" -r 'echo password_hash("test-password", PASSWORD_BCRYPT, ["cost" => 4]);')"

FAKE_PHP="$WORK/fake-php"
cat >"$FAKE_PHP" <<EOF
#!/usr/bin/env bash
if [[ "\$1" == "-r" ]]; then
  exec "$REAL_PHP" "\$@"
fi
printf '%s\n' "\$*" >>"\${FAKE_PHP_LOG:?}"
if [[ "\$*" == *doctrine:migrations:migrate* && -n "\${FAKE_FAIL_MIGRATE:-}" ]]; then
  exit 3
fi
exit 0
EOF
chmod +x "$FAKE_PHP"
touch "$WORK/composer.phar"

make_env() {
  local dir="$1" app_env="$2" db="$3" extra="${4-}"
  cat >"$dir/.env.local" <<EOF
APP_ENV=$app_env
APP_SECRET=0123456789abcdef
DATABASE_URL="mysql://u:p@127.0.0.1:3306/$db?serverVersion=8.4&charset=utf8mb4"
MAILER_DSN=null://null
STAGING_AUTH_ENABLED=1
STAGING_AUTH_USER=dev
STAGING_AUTH_HASH='$HASH'
$extra
EOF
}

make_frontend_archive() {
  local sha="$1" target="$2" tmp
  tmp="$(mktemp -d -p "$WORK")"
  mkdir -p "$tmp/build/.vite" "$tmp/build/assets"
  echo '{}' >"$tmp/build/.vite/manifest.json"
  echo "$3" >"$tmp/build/assets/marker.txt"
  printf '%s\n' "$sha" >"$tmp/BUILD_COMMIT"
  tar -czf "$target" -C "$tmp" .
}

ORIGIN="$WORK/origin.git"
SEED="$WORK/seed"
git init -q --bare -b dev "$ORIGIN"
git init -q -b dev "$SEED"
git -C "$SEED" config user.email test@example.test
git -C "$SEED" config user.name test
mkdir -p "$SEED/bin" "$SEED/src"
printf '.env.local\n/var/\n/vendor/\n/public_html/build/\n' >"$SEED/.gitignore"
echo '<?php' >"$SEED/bin/console"
echo one >"$SEED/src/app.txt"
git -C "$SEED" add -A && git -C "$SEED" commit -qm c1
SHA1="$(git -C "$SEED" rev-parse HEAD)"
echo two >"$SEED/src/app.txt"
git -C "$SEED" commit -qam c2
SHA2="$(git -C "$SEED" rev-parse HEAD)"
git -C "$SEED" remote add origin "$ORIGIN"
git -C "$SEED" push -q origin dev

new_app() {
  local dir="$1"
  rm -rf "$dir"
  mkdir -p "$dir"
  git -C "$dir" init -q -b main
  git -C "$dir" remote add origin "$ORIGIN"
  git -C "$dir" fetch -q origin
  git -C "$dir" checkout -q -f -B staging-deployed "$SHA1"
  make_env "$dir" staging zp_staging
}

run_deploy() {
  local dir="$1"; shift
  local out status=0
  out="$(
    DEPLOY_PATH="$dir" \
    DEPLOY_PROD_PATH="$WORK/prod" \
    PHP_BIN="$FAKE_PHP" \
    COMPOSER_PHAR="$WORK/composer.phar" \
    FAKE_PHP_LOG="$WORK/php.log" \
    bash -s -- "$@" <"$DEPLOY_SCRIPT" 2>&1
  )" || status=$?
  LAST_OUT="$out"
  LAST_STATUS="$status"
}

APP="$WORK/stg"
mkdir -p "$WORK/prod"
make_env "$WORK/prod" prod zp_prod

echo "# успешный деплой"
new_app "$APP"
: >"$WORK/php.log"
mkdir -p "$APP/public_html/build"
echo old >"$APP/public_html/build/marker.txt"
mkdir -p "$APP/var/sessions/staging" "$APP/var/cache/staging"
echo keep >"$APP/var/sessions/staging/sess_abc"
echo stale >"$APP/var/cache/staging/stale.php"
ARCHIVE="$WORK/frontend.tar.gz"
make_frontend_archive "$SHA2" "$ARCHIVE" new
cp "$ARCHIVE" "$WORK/frontend-copy.tar.gz"
FRONTEND_BUILD_ARCHIVE="$WORK/frontend-copy.tar.gz" run_deploy "$APP" "$SHA2"
assert_eq "код выхода 0" "$LAST_STATUS" "0"
assert_eq "HEAD = целевой коммит" "$(git -C "$APP" rev-parse HEAD)" "$SHA2"
assert_eq "локальная ветка staging-deployed" "$(git -C "$APP" rev-parse --abbrev-ref HEAD)" "staging-deployed"
assert_file_contains "composer install --no-dev" "$WORK/php.log" "install --no-dev --optimize-autoloader"
assert_file_contains "миграции применены" "$WORK/php.log" "doctrine:migrations:migrate --env=staging --allow-no-migration --no-interaction"
assert_file_contains "app:smoke:test выполнен" "$WORK/php.log" "app:smoke:test --env=staging"
assert_file_contains "новый frontend установлен" "$APP/public_html/build/assets/marker.txt" "new"
assert_not_exists "предыдущий build удалён после успеха" "$APP/public_html/build.prev"
assert_not_exists "архив frontend удалён" "$WORK/frontend-copy.tar.gz"
assert_file_contains "release-info.json содержит коммит" "$APP/var/release-info.json" "$SHA2"
assert_file_contains "история деплоя записана" "$APP/var/log/deploy-history.log" "$SHA2"
assert_eq "var/cache — симлинк в shared/cache" "$(readlink "$APP/var/cache")" "$APP/shared/cache"
assert_eq "var/sessions — симлинк в shared/sessions" "$(readlink "$APP/var/sessions")" "$APP/shared/sessions"
assert_file_contains "существующие сессии перенесены в shared" "$APP/shared/sessions/staging/sess_abc" "keep"
assert_not_exists "старый кэш очищен" "$APP/shared/cache/staging/stale.php"
assert_eq "кэш прогрет после миграций" "$(grep -n -E 'migrations:migrate|cache:warmup' "$WORK/php.log" | sed -E 's/^[0-9]+:[^ ]* ?//' | cut -d' ' -f1 | tr '\n' ' ')" "doctrine:migrations:migrate cache:warmup "
assert_file_contains ".env.local не затронут" "$APP/.env.local" "APP_ENV=staging"

echo "# деплой ветки по умолчанию (origin/dev) и идемпотентность"
: >"$WORK/php.log"
echo session2 >"$APP/shared/sessions/staging/sess_def"
run_deploy "$APP"
assert_eq "повторный деплой успешен" "$LAST_STATUS" "0"
assert_file_contains "сессии переживают деплой" "$APP/shared/sessions/staging/sess_def" "session2"
assert_eq "HEAD = origin/dev" "$(git -C "$APP" rev-parse HEAD)" "$SHA2"

echo "# откат при падении миграции"
new_app "$APP"
: >"$WORK/php.log"
mkdir -p "$APP/public_html/build"
echo old >"$APP/public_html/build/marker.txt"
make_frontend_archive "$SHA2" "$WORK/frontend-copy.tar.gz" new
FAKE_FAIL_MIGRATE=1 FRONTEND_BUILD_ARCHIVE="$WORK/frontend-copy.tar.gz" run_deploy "$APP" "$SHA2"
assert_eq "ненулевой код выхода" "$([[ "$LAST_STATUS" != "0" ]] && echo yes || echo no)" "yes"
assert_eq "код откачен на предыдущий коммит" "$(git -C "$APP" rev-parse HEAD)" "$SHA1"
assert_file_contains "предыдущий frontend восстановлен" "$APP/public_html/build/marker.txt" "old"
assert_contains "в логе есть откат" "$LAST_OUT" "откатываю код"

echo "# frontend собран из другого коммита"
new_app "$APP"
make_frontend_archive "$SHA1" "$WORK/frontend-copy.tar.gz" stale
FRONTEND_BUILD_ARCHIVE="$WORK/frontend-copy.tar.gz" run_deploy "$APP" "$SHA2"
assert_eq "деплой отклонён" "$([[ "$LAST_STATUS" != "0" ]] && echo yes || echo no)" "yes"
assert_contains "причина в логе" "$LAST_OUT" "Frontend собран из коммита"
assert_eq "код откачен" "$(git -C "$APP" rev-parse HEAD)" "$SHA1"

echo "# preflight: защита от неверной конфигурации"
new_app "$APP"
make_env "$APP" prod zp_staging
run_deploy "$APP" "$SHA2"
assert_contains "APP_ENV не staging" "$LAST_OUT" "APP_ENV в .env.local должен быть staging"
assert_eq "код не менялся" "$(git -C "$APP" rev-parse HEAD)" "$SHA1"

new_app "$APP"
make_env "$APP" staging zp_prod
run_deploy "$APP" "$SHA2"
assert_contains "та же БД, что у прода" "$LAST_OUT" "ту же БД, что и прод"

new_app "$APP"
make_env "$APP" staging zp_staging
sed -i 's|^DATABASE_URL=.*|DATABASE_URL="postgresql://u:p@127.0.0.1:5432/zp_staging?serverVersion=18"|' "$APP/.env.local"
run_deploy "$APP" "$SHA2"
assert_contains "не MySQL DATABASE_URL" "$LAST_OUT" "должен начинаться с mysql://"

new_app "$APP"
sed -i 's|@127.0.0.1:3306|@mysql:3306|' "$APP/.env.local"
run_deploy "$APP" "$SHA2"
assert_contains "Docker-хост mysql" "$LAST_OUT" "Docker-хост mysql"

new_app "$APP"
sed -i "s|^STAGING_AUTH_HASH=.*|STAGING_AUTH_HASH=$HASH|" "$APP/.env.local"
run_deploy "$APP" "$SHA2"
assert_contains "хеш без кавычек" "$LAST_OUT" "ОДИНАРНЫХ кавычках"

new_app "$APP"
sed -i '/^STAGING_AUTH_ENABLED=/d' "$APP/.env.local"
run_deploy "$APP" "$SHA2"
assert_contains "Basic Auth не включён" "$LAST_OUT" "STAGING_AUTH_ENABLED=1 обязателен"

new_app "$APP"
sed -i 's/^APP_SECRET=.*/APP_SECRET=change-me-in-env-local/' "$APP/.env.local"
run_deploy "$APP" "$SHA2"
assert_contains "шаблонный APP_SECRET" "$LAST_OUT" "APP_SECRET"

new_app "$APP"
rm "$APP/.env.local"
run_deploy "$APP" "$SHA2"
assert_contains "нет .env.local" "$LAST_OUT" "Нет $APP/.env.local"

echo "# preflight: каталог прода"
git -C "$WORK/prod" init -q -b main
make_env "$WORK/prod" staging zp_other
ln -sfn "$WORK/prod" "$WORK/prod-link"
DEPLOY_PROD_PATH="$WORK/prod" DEPLOY_PATH="$WORK/prod-link" PHP_BIN="$FAKE_PHP" COMPOSER_PHAR="$WORK/composer.phar" \
  FAKE_PHP_LOG="$WORK/php.log" bash -s -- "$SHA2" <"$DEPLOY_SCRIPT" >"$WORK/prod.out" 2>&1 || true
assert_file_contains "каталог прода (через симлинк) отклонён" "$WORK/prod.out" "каталог прода"

echo "# аргументы"
run_deploy "$APP" --rollback
assert_contains "--rollback не поддерживается" "$LAST_OUT" "не поддерживается"
run_deploy "$APP" 'bad ref;rm'
assert_contains "небезопасный ref отклонён" "$LAST_OUT" "Недопустимый ref"

echo "# хелперы"
# shellcheck source=tools/deploy/deploy-beget.sh
DEPLOY_SOURCE_ONLY=1 source "$DEPLOY_SCRIPT"
# shellcheck disable=SC2088
assert_eq "expand_home ~/x" "$(HOME=/home/u expand_home '~/dev.site.ru')" "/home/u/dev.site.ru"
assert_eq "expand_home абсолютный путь" "$(expand_home /srv/app)" "/srv/app"
assert_eq "имя БД из URL" "$(database_name_from_url 'mysql://u:p@db.local:3306/zp_stg?serverVersion=8.4&charset=utf8mb4')" "zp_stg"
assert_eq "хост БД из URL" "$(database_host_from_url 'mysql://u:p@db.local:3306/zp_stg?serverVersion=8.4&charset=utf8mb4')" "db.local"

echo
echo "Пройдено: $PASS, провалено: $FAIL"
[[ "$FAIL" -eq 0 ]]
