#!/usr/bin/env bash
#
# Деплой staging-стенда dev.zaborprofil.ru на хостинг Beget (native stack, без Docker).
#
# Скрипт не хранится на сервере как источник правды: GitHub Actions передаёт его
# по SSH через stdin:
#
#   ssh deploy-target "DEPLOY_PATH='~/dev.zaborprofil.ru' bash -s -- <sha|ветка>" < tools/deploy/deploy-beget.sh
#
# Без аргументов деплоится origin/dev. Production этим скриптом не деплоится:
# preflight отказывается работать, если APP_ENV в .env.local не равен staging.
# Basic Auth (STAGING_AUTH_ENABLED=1) обязателен; временно открытый staging допустим только при
# STAGING_AUTH_ENABLED=0 и явном STAGING_ALLOW_PUBLIC=1 в .env.local (с предупреждением в логе).
#
# Переменные окружения (все необязательные):
#   DEPLOY_PATH            каталог staging-клона (по умолчанию ~/dev.zaborprofil.ru)
#   DEPLOY_PROD_PATH       каталог прода: защита от путаницы стендов (по умолчанию ~/zaborprofil.ru)
#   DEPLOY_SHARED_DIR      постоянные данные между деплоями: cache/ и sessions/ (по умолчанию <DEPLOY_PATH>/shared)
#   PHP_BIN                CLI PHP (по умолчанию /usr/local/bin/php8.5)
#   COMPOSER_PHAR          composer.phar (по умолчанию ~/composer.phar)
#   DEPLOY_REMOTE          git remote (по умолчанию origin)
#   DEPLOY_DEFAULT_REF     ветка по умолчанию (по умолчанию dev)
#   FRONTEND_BUILD_ARCHIVE tar.gz с prebuilt frontend (build/ + BUILD_COMMIT), собранный в GitHub Actions
#   DEPLOY_HEALTHCHECK     0 отключает app:smoke:test после деплоя
#   DEPLOY_SOURCE_ONLY     1 только определяет функции (для tests/shell/deploy-beget.sh)

set -Eeuo pipefail

REQUIRED_PHP_VERSION_ID=80500
REQUIRED_PHP_EXTENSIONS=(ctype iconv intl mbstring pdo_mysql xml)
STAGING_BRANCH_NAME="staging-deployed"
CLEAN_PATHS=(assets bin config migrations src templates tests tools translations)
KEEP_LOG_LINES=2000

APP_DIR=""
LOG_FILE=""
HISTORY_FILE=""
PREV_REV=""
PREV_BUILD_BACKUP=""
ROLLBACK_ARMED=0
TARGET_SHA=""
LOCK_DIR=""
SHARED_DIR=""
ARCHIVE_PATH=""
PUBLIC_STAGING=0
PUBLIC_STAGING_WARNING="Basic Auth ВЫКЛЮЧЕН (STAGING_AUTH_ENABLED=0, STAGING_ALLOW_PUBLIC=1): staging открыт всем. Индексация закрыта (noindex, robots.txt Disallow: /), /admin защищён формой входа. Чтобы вернуть пароль: STAGING_AUTH_ENABLED=1 и убрать STAGING_ALLOW_PUBLIC."

log() {
  printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

warn() {
  printf '[%s] ВНИМАНИЕ: %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >&2
}

fail() {
  printf '[%s] ОШИБКА: %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >&2
  exit 1
}

expand_home() {
  local path="$1" tilde='~'
  case "$path" in
    "$tilde") printf '%s' "$HOME" ;;
    "$tilde"/*) printf '%s' "$HOME/${path#"$tilde"/}" ;;
    *) printf '%s' "$path" ;;
  esac
}

# Читает KEY из .env.local, затем из .env. Снимает внешние одинарные/двойные кавычки.
env_value() {
  local key="$1" file line value
  for file in "$APP_DIR/.env.local" "$APP_DIR/.env"; do
    [[ -f "$file" ]] || continue
    line="$(grep -E "^${key}=" "$file" | tail -n 1 || true)"
    [[ -n "$line" ]] || continue
    value="${line#*=}"
    value="${value%"${value##*[![:space:]]}"}"
    if [[ "$value" =~ ^\'(.*)\'$ ]] || [[ "$value" =~ ^\"(.*)\"$ ]]; then
      value="${BASH_REMATCH[1]}"
    fi
    printf '%s' "$value"
    return 0
  done
  return 1
}

# Имя БД из DATABASE_URL вида mysql://user:pass@host:3306/dbname?serverVersion=8.4&charset=utf8mb4
database_name_from_url() {
  local url="$1" rest
  rest="${url#*://}"
  rest="${rest#*/}"
  printf '%s' "${rest%%\?*}"
}

database_host_from_url() {
  local url="$1" rest
  rest="${url#*://}"
  rest="${rest#*@}"
  rest="${rest%%/*}"
  printf '%s' "${rest%%:*}"
}

# Каноничный путь каталога (с разворачиванием симлинков); пустая строка, если каталога нет.
real_dir() {
  local dir="$1"
  [[ -d "$dir" ]] || return 0
  (cd "$dir" && pwd -P)
}

# Basic Auth обязателен. Исключение — временный открытый staging: STAGING_AUTH_ENABLED=0 допустим
# только вместе с явным STAGING_ALLOW_PUBLIC=1. Защита от индексации (X-Robots-Tag, meta robots,
# robots.txt Disallow: /) от Basic Auth не зависит и действует всегда при APP_ENV=staging.
check_staging_access() {
  local auth_enabled allow_public hash_line

  auth_enabled="$(env_value STAGING_AUTH_ENABLED || true)"
  allow_public="$(env_value STAGING_ALLOW_PUBLIC || true)"

  if [[ "$auth_enabled" == "0" && "$allow_public" == "1" ]]; then
    PUBLIC_STAGING=1
    warn "$PUBLIC_STAGING_WARNING"
    return 0
  fi

  [[ "$auth_enabled" == "1" ]] \
    || fail "STAGING_AUTH_ENABLED=1 обязателен: staging должен быть закрыт Basic Auth. Открытый staging разрешён только при STAGING_AUTH_ENABLED=0 и STAGING_ALLOW_PUBLIC=1 в .env.local."
  [[ -n "$(env_value STAGING_AUTH_USER || true)" ]] || fail "STAGING_AUTH_USER не задан."

  hash_line="$(grep -E '^STAGING_AUTH_HASH=' "$APP_DIR/.env.local" | tail -n 1 || true)"
  if ! [[ "$hash_line" =~ ^STAGING_AUTH_HASH=\'\$2[aby]\$[0-9]{2}\$[./A-Za-z0-9]{53}\'[[:space:]]*$ ]]; then
    fail "STAGING_AUTH_HASH должен быть bcrypt-хешем в ОДИНАРНЫХ кавычках (Symfony Dotenv раскрывает \$... без кавычек)."
  fi
}

staging_preflight() {
  local prod_dir app_real prod_real app_env db_url db_name prod_db_url prod_db_name
  local secret mailer

  prod_dir="$(expand_home "${DEPLOY_PROD_PATH:-~/zaborprofil.ru}")"
  app_real="$(real_dir "$APP_DIR")"
  prod_real="$(real_dir "$prod_dir")"

  if [[ -n "$prod_real" && "$app_real" == "$prod_real" ]]; then
    fail "$APP_DIR — это каталог прода ($prod_dir). Этот скрипт деплоит только staging."
  fi

  [[ -f "$APP_DIR/.env.local" ]] || fail "Нет $APP_DIR/.env.local. Создайте его по шаблону .env.staging.beget.example."

  app_env="$(env_value APP_ENV || true)"
  [[ "$app_env" == "staging" ]] || fail "APP_ENV в .env.local должен быть staging (сейчас: '${app_env:-не задан}')."

  db_url="$(env_value DATABASE_URL || true)"
  [[ -n "$db_url" ]] || fail "DATABASE_URL не задан в .env.local."
  [[ "$db_url" == mysql://* ]] || fail "DATABASE_URL должен начинаться с mysql:// (MySQL 8.4), сейчас: '${db_url%%://*}://...'."
  [[ "$db_url" != *"@mysql:"* ]] || fail "DATABASE_URL указывает на Docker-хост mysql: это значение из локальной разработки."
  [[ "$db_url" == *serverVersion=8.4* ]] || warn "В DATABASE_URL нет serverVersion=8.4: Doctrine будет определять версию сервера запросом."
  [[ "$db_url" == *charset=utf8mb4* ]] || warn "В DATABASE_URL нет charset=utf8mb4."
  db_name="$(database_name_from_url "$db_url")"
  [[ -n "$db_name" ]] || fail "Не удалось определить имя БД из DATABASE_URL."

  if [[ -n "$prod_real" ]]; then
    prod_db_url="$(APP_DIR="$prod_real" env_value DATABASE_URL || true)"
    if [[ -n "$prod_db_url" ]]; then
      prod_db_name="$(database_name_from_url "$prod_db_url")"
      if [[ "$prod_db_name" == "$db_name" && "$(database_host_from_url "$prod_db_url")" == "$(database_host_from_url "$db_url")" ]]; then
        fail "Staging использует ту же БД, что и прод ($db_name). Создайте отдельную БД."
      fi
    fi
  fi

  secret="$(env_value APP_SECRET || true)"
  if [[ -z "$secret" || "$secret" == change-me* || "$secret" == change-this* ]]; then
    fail "APP_SECRET не задан или остался шаблонным значением."
  fi

  check_staging_access

  if [[ -n "$(env_value REDIS_URL || true)" ]]; then
    warn "REDIS_URL задан, но Redis не используется: кэш приложения файловый (var/cache/staging/pools)."
  fi

  mailer="$(env_value MAILER_DSN || true)"
  if [[ "$mailer" != null://* ]]; then
    warn "MAILER_DSN на staging не null://null: со стенда могут уйти реальные письма."
  fi
}

# var/cache и var/sessions живут в shared/ и переживают checkout/clean; код всегда ходит через симлинки.
link_shared_dirs() {
  local name target link

  mkdir -p "$APP_DIR/var"
  for name in cache sessions; do
    target="$SHARED_DIR/$name"
    link="$APP_DIR/var/$name"
    mkdir -p "$target"

    if [[ -L "$link" && "$(real_dir "$link")" == "$(real_dir "$target")" ]]; then
      continue
    fi

    if [[ -d "$link" && ! -L "$link" && "$name" == "sessions" ]]; then
      cp -R -- "$link/." "$target/" 2>/dev/null || true
    fi
    rm -rf -- "$link"
    ln -s -- "$target" "$link"
  done
  chmod 700 "$SHARED_DIR/sessions"
}

setup_logging() {
  mkdir -p "$APP_DIR/var/log"
  LOG_FILE="$APP_DIR/var/log/deploy.log"
  HISTORY_FILE="$APP_DIR/var/log/deploy-history.log"
  exec > >(tee -a "$LOG_FILE") 2>&1
}

acquire_lock() {
  local lock_file="$APP_DIR/var/deploy.lock"

  if command -v flock >/dev/null 2>&1; then
    exec 9>"$lock_file"
    flock -n 9 || fail "Другой деплой уже выполняется ($lock_file)."
    return
  fi

  LOCK_DIR="$APP_DIR/var/deploy.lock.d"
  mkdir "$LOCK_DIR" 2>/dev/null || { local busy="$LOCK_DIR"; LOCK_DIR=""; fail "Другой деплой уже выполняется. Если это не так, удалите $busy."; }
}

cleanup() {
  local exit_code=$?

  trap - EXIT ERR
  set +e
  if ((ROLLBACK_ARMED == 1)) && ((exit_code != 0)); then
    rollback_code "$exit_code"
  fi
  [[ -z "$LOCK_DIR" ]] || rm -rf "$LOCK_DIR"
  [[ -z "$ARCHIVE_PATH" ]] || rm -f "$ARCHIVE_PATH"
  exit "$exit_code"
}

check_php() {
  local ext missing=()

  [[ -x "$PHP_BIN" ]] || fail "Не найден PHP: $PHP_BIN. Посмотрите 'ls /usr/local/bin/php*' и задайте PHP_BIN."

  "$PHP_BIN" -r "exit(PHP_VERSION_ID >= ${REQUIRED_PHP_VERSION_ID} ? 0 : 1);" \
    || fail "$PHP_BIN — не PHP 8.5+ ($("$PHP_BIN" -r 'echo PHP_VERSION;'))."

  for ext in "${REQUIRED_PHP_EXTENSIONS[@]}"; do
    "$PHP_BIN" -r "exit(extension_loaded('${ext}') ? 0 : 1);" || missing+=("$ext")
  done
  if ((${#missing[@]} > 0)); then
    fail "В $PHP_BIN нет расширений: ${missing[*]}. Включите их в панели Beget или смените тариф/сервер."
  fi

  [[ -f "$COMPOSER_PHAR" ]] || fail "Не найден Composer: $COMPOSER_PHAR (см. docs/49-beget-staging-deploy.md, раздел про Composer)."
}

composer_install() {
  "$PHP_BIN" -d memory_limit=-1 "$COMPOSER_PHAR" install \
    --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist \
    --working-dir="$APP_DIR" </dev/null
}

console() {
  (cd "$APP_DIR" && "$PHP_BIN" bin/console "$@" --no-interaction </dev/null)
}

resolve_target() {
  local ref="$1" remote="$2"

  git -C "$APP_DIR" fetch --prune --quiet "$remote"

  if [[ "$ref" =~ ^[0-9a-f]{40}$ ]]; then
    if ! git -C "$APP_DIR" cat-file -e "${ref}^{commit}" 2>/dev/null; then
      git -C "$APP_DIR" fetch --quiet "$remote" "$ref" || fail "Коммит $ref не найден в $remote."
    fi
    TARGET_SHA="$ref"
  elif git -C "$APP_DIR" rev-parse --verify --quiet "refs/remotes/${remote}/${ref}^{commit}" >/dev/null; then
    TARGET_SHA="$(git -C "$APP_DIR" rev-parse "refs/remotes/${remote}/${ref}^{commit}")"
  else
    git -C "$APP_DIR" fetch --quiet "$remote" "$ref" || fail "Ref '$ref' не найден в $remote."
    TARGET_SHA="$(git -C "$APP_DIR" rev-parse "FETCH_HEAD^{commit}")"
  fi
}

ensure_clean_tree() {
  local changes
  changes="$(git -C "$APP_DIR" status --porcelain --untracked-files=no)"
  if [[ -n "$changes" ]]; then
    warn "На сервере есть локальные правки отслеживаемых файлов, они будут перезаписаны (checkout --force):"
    printf '%s\n' "$changes" >&2
  fi
}

checkout_staging() {
  local sha="$1"

  git -C "$APP_DIR" checkout --force --quiet -B "$STAGING_BRANCH_NAME" "$sha"
  # Только неотслеживаемые, но не игнорируемые файлы: .env.local, vendor/, var/, uploads и build/ остаются.
  git -C "$APP_DIR" clean -fdq -- "${CLEAN_PATHS[@]}"
}

install_prebuilt_frontend() {
  local archive="$1" sha="$2"
  local extract_dir="$APP_DIR/var/frontend-extract" built_commit build_dir="$APP_DIR/public_html/build"

  [[ -f "$archive" ]] || fail "Архив frontend не найден: $archive"

  log "Устанавливаю prebuilt frontend из $archive"
  rm -rf "$extract_dir"
  mkdir -p "$extract_dir"
  tar -xzf "$archive" --no-same-owner --no-same-permissions -C "$extract_dir"

  [[ -f "$extract_dir/build/.vite/manifest.json" ]] || fail "В архиве нет build/.vite/manifest.json."

  built_commit="$(tr -d '[:space:]' <"$extract_dir/BUILD_COMMIT" 2>/dev/null || true)"
  if [[ "$built_commit" != "$sha" ]]; then
    fail "Frontend собран из коммита '${built_commit:-неизвестно}', а деплоится $sha."
  fi

  mkdir -p "$APP_DIR/public_html"
  rm -rf "$APP_DIR/public_html/build.prev"
  if [[ -d "$build_dir" ]]; then
    mv "$build_dir" "$APP_DIR/public_html/build.prev"
    PREV_BUILD_BACKUP="$APP_DIR/public_html/build.prev"
  fi
  mv "$extract_dir/build" "$build_dir"
  rm -rf "$extract_dir"
}

write_release_info() {
  local sha="$1"
  mkdir -p "$APP_DIR/var"
  printf '{"release":"%s","commit":"%s","deployed_at":"%s"}\n' \
    "${sha:0:12}" "$sha" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" >"$APP_DIR/var/release-info.json"
}

# Чистит содержимое shared/cache (контейнер, Twig и файловый кэш приложения var/cache/staging/pools),
# сам каталог и симлинк var/cache остаются. Сессии не трогаются.
clear_caches() {
  mkdir -p "$SHARED_DIR/cache"
  find "$SHARED_DIR/cache" -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +
}

run_health_check() {
  if [[ "${DEPLOY_HEALTHCHECK:-1}" == "0" ]]; then
    warn "Health-check отключён (DEPLOY_HEALTHCHECK=0)."
    return
  fi

  log "Health-check: app:smoke:test"
  console app:smoke:test --env=staging
}

rollback_code() {
  local exit_code="$1"

  ROLLBACK_ARMED=0
  if [[ -z "$PREV_REV" ]]; then
    warn "Деплой упал (код $exit_code), предыдущего коммита нет: откатывать нечего."
    return
  fi

  warn "Деплой упал (код $exit_code), откатываю код на $PREV_REV. Изменения БД от миграций не откатываются: восстановление только из бэкапа."
  if checkout_staging "$PREV_REV" \
    && restore_previous_frontend \
    && composer_install \
    && clear_caches; then
    log "Откат выполнен: $PREV_REV"
    record_history "rollback" "$PREV_REV"
  else
    printf 'КРИТИЧНО: автооткат не удался. Разбирайтесь вручную: git -C %s status\n' "$APP_DIR" >&2
  fi
}

restore_previous_frontend() {
  [[ -n "$PREV_BUILD_BACKUP" && -d "$PREV_BUILD_BACKUP" ]] || return 0
  rm -rf "$APP_DIR/public_html/build" && mv "$PREV_BUILD_BACKUP" "$APP_DIR/public_html/build"
}

record_history() {
  local action="$1" sha="$2"
  printf '%s\t%s\t%s\t%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$action" "$sha" "$(id -un)" >>"$HISTORY_FILE"
  if [[ -f "$HISTORY_FILE" ]] && (($(wc -l <"$HISTORY_FILE") > KEEP_LOG_LINES)); then
    tail -n "$KEEP_LOG_LINES" "$HISTORY_FILE" >"$HISTORY_FILE.tmp" && mv "$HISTORY_FILE.tmp" "$HISTORY_FILE"
  fi
}

parse_args() {
  local arg ref=""
  for arg in "$@"; do
    case "$arg" in
      --staging) ;;
      --rollback | --migrate) fail "Аргумент $arg не поддерживается: скрипт деплоит только staging и всегда применяет миграции." ;;
      -*) fail "Неизвестный аргумент: $arg" ;;
      *)
        [[ -z "$ref" ]] || fail "Ожидается один ref, получено несколько."
        ref="$arg"
        ;;
    esac
  done
  [[ -z "$ref" || "$ref" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]*$ ]] || fail "Недопустимый ref: '$ref'."
  printf '%s' "${ref:-${DEPLOY_DEFAULT_REF:-dev}}"
}

main() {
  local ref remote archive
  trap cleanup EXIT

  ref="$(parse_args "$@")"
  remote="${DEPLOY_REMOTE:-origin}"
  PHP_BIN="$(expand_home "${PHP_BIN:-/usr/local/bin/php8.5}")"
  COMPOSER_PHAR="$(expand_home "${COMPOSER_PHAR:-~/composer.phar}")"
  APP_DIR="$(expand_home "${DEPLOY_PATH:-~/dev.zaborprofil.ru}")"
  SHARED_DIR="$(expand_home "${DEPLOY_SHARED_DIR:-$APP_DIR/shared}")"
  archive="$(expand_home "${FRONTEND_BUILD_ARCHIVE:-}")"
  ARCHIVE_PATH="$archive"

  [[ -d "$APP_DIR/.git" ]] || fail "$APP_DIR не git-клон. Выполните первичную настройку (docs/49-beget-staging-deploy.md, раздел 3)."

  staging_preflight
  setup_logging
  acquire_lock
  log "=== staging: старт, ref=$ref, пользователь $(id -un) ==="
  if ((PUBLIC_STAGING == 1)); then
    warn "$PUBLIC_STAGING_WARNING"
  fi
  check_php

  resolve_target "$ref" "$remote"
  PREV_REV="$(git -C "$APP_DIR" rev-parse --verify --quiet HEAD || true)"
  log "Цель: $TARGET_SHA (было: ${PREV_REV:-пусто})"

  ensure_clean_tree

  ROLLBACK_ARMED=1

  link_shared_dirs
  checkout_staging "$TARGET_SHA"
  composer_install

  if [[ -n "$archive" ]]; then
    install_prebuilt_frontend "$archive" "$TARGET_SHA"
  else
    warn "FRONTEND_BUILD_ARCHIVE не задан: public_html/build остаётся как есть (Node.js на сервере не используется)."
  fi

  write_release_info "$TARGET_SHA"
  clear_caches

  log "Миграции Doctrine"
  console doctrine:migrations:migrate --env=staging --allow-no-migration

  log "Прогрев кэша"
  console cache:warmup --env=staging
  run_health_check

  ROLLBACK_ARMED=0
  rm -rf "$APP_DIR/public_html/build.prev"
  record_history "deploy" "$TARGET_SHA"
  log "=== staging завершён: $TARGET_SHA ==="
}

if [[ "${DEPLOY_SOURCE_ONLY:-0}" != "1" ]]; then
  main "$@"
fi
