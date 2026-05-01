#!/usr/bin/env bash

set -Eeuo pipefail

APP_NAME="${APP_NAME:-zaborprofil}"
APP_ROOT="${APP_ROOT:-/var/www/zaborprofil}"
REPOSITORY="${REPOSITORY:-git@github.com:ariestop/zaborprofil.git}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"
NGINX_SERVICE="${NGINX_SERVICE:-nginx}"
WORKER_SERVICE="${WORKER_SERVICE:-zaborprofil-messenger}"

RELEASES_DIR="${APP_ROOT}/releases"
SHARED_DIR="${APP_ROOT}/shared"
CURRENT_LINK="${APP_ROOT}/current"
BACKUP_DIR="${SHARED_DIR}/backups"

log() {
  printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Command not found: $1"
}

prepare_shared_layout() {
  mkdir -p \
    "$RELEASES_DIR" \
    "$SHARED_DIR/public_html/uploads" \
    "$SHARED_DIR/var/log" \
    "$BACKUP_DIR"

  if [[ ! -f "$SHARED_DIR/.env.local" ]]; then
    fail "Missing shared env: $SHARED_DIR/.env.local"
  fi
}

clone_release() {
  local branch="$1"
  local release_dir="$2"

  git clone --depth=1 --branch "$branch" "$REPOSITORY" "$release_dir"
}

link_shared_paths() {
  local release_dir="$1"

  ln -sfn "$SHARED_DIR/.env.local" "$release_dir/.env.local"
  rm -rf "$release_dir/public_html/uploads" "$release_dir/var/log"
  mkdir -p "$release_dir/public_html" "$release_dir/var"
  ln -sfn "$SHARED_DIR/public_html/uploads" "$release_dir/public_html/uploads"
  ln -sfn "$SHARED_DIR/var/log" "$release_dir/var/log"
}

install_release_dependencies() {
  local release_dir="$1"

  (cd "$release_dir" && "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader)
  (cd "$release_dir" && "$NPM_BIN" ci)
  (cd "$release_dir" && "$NPM_BIN" run build)
}

run_release_console_tasks() {
  local release_dir="$1"
  local app_env="$2"

  (cd "$release_dir" && "$PHP_BIN" bin/console doctrine:migrations:migrate --no-interaction --env="$app_env")
  (cd "$release_dir" && "$PHP_BIN" bin/console cache:clear --no-warmup --env="$app_env")
  (cd "$release_dir" && "$PHP_BIN" bin/console cache:warmup --env="$app_env")
}

restart_services() {
  if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload-or-restart "$PHP_FPM_SERVICE"
    sudo systemctl reload "$NGINX_SERVICE"
    sudo systemctl restart "$WORKER_SERVICE"
  else
    log "systemctl is not available, skip service restart"
  fi
}

switch_current() {
  local release_dir="$1"

  ln -sfn "$release_dir" "$CURRENT_LINK"
}

rollback_to() {
  local release_dir="$1"

  [[ -n "$release_dir" && -d "$release_dir" ]] || fail "Rollback target does not exist: $release_dir"
  switch_current "$release_dir"
  restart_services
}

cleanup_old_releases() {
  local keep="$KEEP_RELEASES"

  find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -print0 \
    | xargs -0 ls -dt 2>/dev/null \
    | tail -n +"$((keep + 1))" \
    | xargs -r rm -rf
}

load_shared_env() {
  set -a
  # shellcheck disable=SC1091
  source "$SHARED_DIR/.env.local"
  set +a
}

backup_database() {
  local release_name="$1"

  if [[ -n "${DATABASE_BACKUP_COMMAND:-}" ]]; then
    log "Run custom database backup command"
    bash -lc "$DATABASE_BACKUP_COMMAND"
    return
  fi

  require_command pg_dump
  load_shared_env

  local backup_file="$BACKUP_DIR/${release_name}_database.dump"

  "$PHP_BIN" <<'PHP' >"$BACKUP_DIR/.pg-env"
<?php
$url = getenv('DATABASE_URL') ?: '';
$parts = parse_url($url);
if ($parts === false || ($parts['scheme'] ?? '') === '') {
    fwrite(STDERR, "DATABASE_URL is not parseable\n");
    exit(1);
}
foreach ([
    'PGHOST' => $parts['host'] ?? '127.0.0.1',
    'PGPORT' => (string) ($parts['port'] ?? 5432),
    'PGDATABASE' => isset($parts['path']) ? ltrim($parts['path'], '/') : '',
    'PGUSER' => $parts['user'] ?? '',
    'PGPASSWORD' => $parts['pass'] ?? '',
] as $key => $value) {
    echo 'export ', $key, '=', escapeshellarg($value), PHP_EOL;
}
PHP

  # shellcheck disable=SC1091
  source "$BACKUP_DIR/.pg-env"
  rm -f "$BACKUP_DIR/.pg-env"

  pg_dump --format=custom --file="$backup_file"
  log "Database backup created: $backup_file"
}

backup_uploads() {
  local release_name="$1"
  local backup_file="$BACKUP_DIR/${release_name}_uploads.tar.gz"

  tar -czf "$backup_file" -C "$SHARED_DIR/public_html" uploads
  log "Uploads backup created: $backup_file"
}

run_health_check() {
  local health_url="$1"

  "$(dirname "${BASH_SOURCE[0]}")/health-check.sh" "$health_url"
}

deploy_release() {
  local branch="$1"
  local app_env="$2"
  local health_url="$3"
  local with_backup="$4"
  local release_name
  local release_dir
  local previous_release

  require_command git
  require_command "$PHP_BIN"
  require_command "$COMPOSER_BIN"
  require_command "$NPM_BIN"
  require_command curl

  prepare_shared_layout

  release_name="$(date '+%Y-%m-%d_%H%M%S')"
  release_dir="$RELEASES_DIR/$release_name"
  previous_release="$(readlink "$CURRENT_LINK" 2>/dev/null || true)"

  log "Create release $release_name from $branch"
  clone_release "$branch" "$release_dir"
  link_shared_paths "$release_dir"
  install_release_dependencies "$release_dir"

  if [[ "$with_backup" == "yes" ]]; then
    backup_database "$release_name"
    backup_uploads "$release_name"
  fi

  run_release_console_tasks "$release_dir" "$app_env"
  switch_current "$release_dir"
  restart_services

  if ! run_health_check "$health_url"; then
    log "Health-check failed"
    if [[ -n "$previous_release" ]]; then
      log "Rollback to previous release: $previous_release"
      rollback_to "$previous_release"
    fi
    exit 1
  fi

  cleanup_old_releases
  log "Deploy finished: $release_name"
}
