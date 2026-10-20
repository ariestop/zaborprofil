#!/usr/bin/env bash
# Выполняется после загрузки функций deploy-beget.sh с DEPLOY_SOURCE_ONLY=1.
# Только чтение; при любой неоднозначности деплой останавливается.
set -Eeuo pipefail

APP_DIR="$(expand_home "${DEPLOY_PATH:?DEPLOY_PATH required}")"
dev1_dir="$(expand_home "${DEV1_PATH:?DEV1_PATH required}")"
prod_dir="$(expand_home "${DEPLOY_PROD_PATH:-~/zaborprofil.ru}")"
app_real="$(real_dir "$APP_DIR")"

[[ -n "$app_real" && -d "$APP_DIR/.git" ]] \
  || fail "dev2 не подготовлен: нужен отдельный git-клон в DEPLOY_PATH (docs/53-dev2-staging.md)."
for protected in "$dev1_dir" "$prod_dir"; do
  protected_real="$(real_dir "$protected")"
  if [[ -n "$protected_real" ]]; then
    case "$app_real/" in
      "$protected_real/"*) fail "Каталог dev2 совпадает с существующим стендом или вложен в него." ;;
    esac
    case "$protected_real/" in
      "$app_real/"*) fail "Каталог dev2 содержит существующий стенд." ;;
    esac
  fi
done

staging_preflight
[[ "$(env_value SITE_URL || true)" == "https://dev2.zaborprofil.ru" ]] \
  || fail "SITE_URL в dev2/.env.local должен быть https://dev2.zaborprofil.ru."
[[ "$(env_value DEFAULT_URI || true)" == "https://dev2.zaborprofil.ru" ]] \
  || fail "DEFAULT_URI в dev2/.env.local должен быть https://dev2.zaborprofil.ru."

[[ -f "$dev1_dir/.env.local" ]] \
  || fail "Не найдена конфигурация dev для проверки независимости БД. Проверьте STAGING_PATH."
dev1_db="$(APP_DIR="$dev1_dir" env_value DATABASE_URL || true)"
dev2_db="$(env_value DATABASE_URL || true)"
[[ -n "$dev1_db" ]] || fail "Нельзя проверить независимость БД: DATABASE_URL dev не найден."
[[ "$(database_name_from_url "$dev1_db")" != "$(database_name_from_url "$dev2_db")" ]] \
  || fail "dev2 использует имя БД dev. Нужна отдельная БД с другим именем."

for relative in shared vendor var public_html var/cache var/sessions public_html/uploads; do
  target="$(real_dir "$APP_DIR/$relative")"
  if [[ -n "$target" ]]; then
    case "$target/" in
      "$app_real/"*) ;;
      *) fail "dev2/$relative ссылается за пределы dev2. Нужен отдельный каталог." ;;
    esac
  fi
done
printf '%s\n' "dev2: отдельный каталог, staging-конфигурация, URL и независимое имя БД подтверждены."
