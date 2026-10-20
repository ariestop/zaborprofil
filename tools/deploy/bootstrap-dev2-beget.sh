#!/usr/bin/env bash
# Загружается после deploy-beget.sh с DEPLOY_SOURCE_ONLY=1; только первичная настройка dev2.
set -Eeuo pipefail
umask 077

APP_DIR="$(expand_home "${DEPLOY_PATH:?DEPLOY_PATH required}")"
dev1_dir="$(expand_home "${DEV1_PATH:?DEV1_PATH required}")"
prod_dir="$(expand_home "${DEPLOY_PROD_PATH:-~/zaborprofil.ru}")"
config_dir="$(expand_home "${DEV2_CONFIG_DIR:?DEV2_CONFIG_DIR required}")"
sha="${BUILD_SHA:?BUILD_SHA required}"
[[ "$sha" =~ ^[0-9a-f]{40}$ ]] || fail "Недопустимый SHA."
[[ "$config_dir" == "$HOME/.deploy-tmp/dev2-bootstrap-$sha" ]] || fail "Недопустимый временный каталог."
cleanup_config() {
  rm -f -- "$config_dir/.env.local"
  rmdir -- "$config_dir" 2>/dev/null || true
}
trap cleanup_config EXIT

app_real="$(real_dir "$APP_DIR")"
[[ -n "$app_real" && "${app_real##*/}" == dev2.zaborprofil.ru ]] \
  || fail "Нужен существующий отдельный каталог dev2.zaborprofil.ru из панели Beget."
for protected in "$dev1_dir" "$prod_dir"; do
  protected_real="$(real_dir "$protected")"
  if [[ -n "$protected_real" ]]; then
    case "$app_real/" in "$protected_real/"*) fail "Каталог dev2 пересекается с другим стендом." ;; esac
    case "$protected_real/" in "$app_real/"*) fail "Каталог dev2 содержит другой стенд." ;; esac
  fi
done
[[ ! -L "$APP_DIR/.git" && ! -L "$APP_DIR/.env.local" && ! -L "$APP_DIR/public_html" ]] \
  || fail "Первичная настройка не допускает симлинки .git, .env.local или public_html."

# Проверяем новую конфигурацию до изменения каталога сайта.
candidate_dir="$APP_DIR"
if [[ ! -f "$APP_DIR/.env.local" ]]; then
  candidate_dir="$config_dir"
fi
APP_DIR="$candidate_dir" staging_preflight
[[ "$(APP_DIR="$candidate_dir" env_value SITE_URL || true)" == https://dev2.zaborprofil.ru ]] || fail "Неверный SITE_URL dev2."
[[ "$(APP_DIR="$candidate_dir" env_value DEFAULT_URI || true)" == https://dev2.zaborprofil.ru ]] || fail "Неверный DEFAULT_URI dev2."
dev2_db="$(APP_DIR="$candidate_dir" env_value DATABASE_URL || true)"
dev1_db="$(APP_DIR="$dev1_dir" env_value DATABASE_URL || true)"
[[ -n "$dev1_db" ]] || fail "Не найдена конфигурация dev для проверки разделения БД."
[[ "$(database_name_from_url "$dev1_db")" != "$(database_name_from_url "$dev2_db")" ]] || fail "dev2 должна использовать отдельную БД."

if [[ ! -d "$APP_DIR/.git" ]]; then
  # Сохраняем только известные стартовые файлы хостинга; произвольный сайт не перезаписываем.
  while IFS= read -r -d '' entry; do
    case "${entry##*/}" in public_html|.env.local|.dev2-bootstrap-backup) ;; *) fail "Каталог dev2 содержит посторонние файлы; нужна проверка владельцем." ;; esac
  done < <(find "$APP_DIR" -mindepth 1 -maxdepth 1 -print0)
  mkdir -p "$APP_DIR/public_html"
  while IFS= read -r -d '' entry; do
    case "${entry##*/}" in index.html|index.php|.htaccess|robots.txt|favicon.ico) ;; *) fail "public_html не пуст: исходные файлы сохранены, требуется проверка владельцем." ;; esac
    [[ -f "$entry" && ! -L "$entry" ]] || fail "Необычный файл в public_html; первичная настройка остановлена."
  done < <(find "$APP_DIR/public_html" -mindepth 1 -maxdepth 1 -print0)
  backup_dir="$APP_DIR/.dev2-bootstrap-backup"
  [[ ! -e "$backup_dir" ]] || fail "Найдена предыдущая резервная копия bootstrap; требуется проверка."
  mkdir -m 700 "$backup_dir"
  while IFS= read -r -d '' entry; do
    mv -- "$entry" "$backup_dir/"
  done < <(find "$APP_DIR/public_html" -mindepth 1 -maxdepth 1 -print0)
  git -C "$APP_DIR" init -q
  git -C "$APP_DIR" remote add origin https://github.com/ariestop/zaborprofil.git
  git -C "$APP_DIR" fetch --quiet --depth=1 origin "$sha"
  git -C "$APP_DIR" checkout --quiet -b staging-deployed "$sha"
  printf '%s\n' "dev2: git-клон подготовлен; стартовые файлы сохранены в .dev2-bootstrap-backup."
fi

if [[ ! -f "$APP_DIR/.env.local" ]]; then
  cp -- "$config_dir/.env.local" "$APP_DIR/.env.local"
  chmod 600 "$APP_DIR/.env.local"
  printf '%s\n' "dev2: отдельная конфигурация создана без вывода секретов."
else
  printf '%s\n' "dev2: существующая конфигурация сохранена."
fi
