#!/usr/bin/env bash

set -Eeuo pipefail

APP_NAME="${APP_NAME:-zaborprofil}"
APP_ROOT="${APP_ROOT:-/var/www/zaborprofil}"
REPOSITORY="${REPOSITORY:-git@github.com:ariestop/zaborprofil.git}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
# Prebuilt frontend (tar.gz made in GitHub Actions, see .github/workflows/deploy.yml).
# When set, the server does not need Node.js: no `npm ci` / `npm run build`.
# When empty, deploy falls back to building on the server (manual deploys).
FRONTEND_BUILD_ARCHIVE="${FRONTEND_BUILD_ARCHIVE:-}"
# Exact commit to deploy. Keeps the code in sync with the prebuilt frontend.
DEPLOY_COMMIT="${DEPLOY_COMMIT:-}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.5-fpm}"
NGINX_SERVICE="${NGINX_SERVICE:-nginx}"
WORKER_SERVICE="${WORKER_SERVICE:-zaborprofil-messenger}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
DEPLOY_LOG_RETENTION_DAYS="${DEPLOY_LOG_RETENTION_DAYS:-90}"

RELEASES_DIR="${APP_ROOT}/releases"
SHARED_DIR="${APP_ROOT}/shared"
CURRENT_LINK="${APP_ROOT}/current"
BACKUP_DIR="${SHARED_DIR}/backups"
DB_BACKUP_DIR="${BACKUP_DIR}/db"
UPLOADS_BACKUP_DIR="${BACKUP_DIR}/uploads"
DEPLOYMENTS_DIR="${SHARED_DIR}/deployments"
DEPLOY_LOG_FILE="${DEPLOY_LOG_FILE:-$DEPLOYMENTS_DIR/deployments.jsonl}"
DEPLOY_LOCK_DIR="${APP_ROOT}/.deploy.lock"
STAGING_MARKER_FILE="${STAGING_MARKER_FILE:-$DEPLOYMENTS_DIR/last-staging-success.env}"

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
    "$DB_BACKUP_DIR" \
    "$UPLOADS_BACKUP_DIR" \
    "$DEPLOYMENTS_DIR"

  if [[ ! -f "$SHARED_DIR/.env.local" ]]; then
    fail "Missing shared env: $SHARED_DIR/.env.local"
  fi
}

acquire_deploy_lock() {
  if ! mkdir "$DEPLOY_LOCK_DIR" 2>/dev/null; then
    fail "Another deploy or rollback is already running: $DEPLOY_LOCK_DIR"
  fi

  printf '%s\n' "$$" >"$DEPLOY_LOCK_DIR/pid"
}

release_deploy_lock() {
  rm -rf "$DEPLOY_LOCK_DIR"
}

append_deployment_log() {
  local action="$1"
  local status="$2"
  local release_name="${3:-}"
  local branch="${4:-}"
  local app_env="${5:-}"
  local commit="${6:-}"
  local message="${7:-}"

  mkdir -p "$DEPLOYMENTS_DIR"

  DEPLOY_LOG_ACTION="$action" \
  DEPLOY_LOG_STATUS="$status" \
  DEPLOY_LOG_RELEASE="$release_name" \
  DEPLOY_LOG_BRANCH="$branch" \
  DEPLOY_LOG_APP_ENV="$app_env" \
  DEPLOY_LOG_COMMIT="$commit" \
  DEPLOY_LOG_MESSAGE="$message" \
  DEPLOY_LOG_USER="$(id -un 2>/dev/null || printf 'unknown')" \
  "$PHP_BIN" <<'PHP' >>"$DEPLOY_LOG_FILE"
<?php
$entry = [
    'timestamp' => gmdate('c'),
    'action' => getenv('DEPLOY_LOG_ACTION') ?: '',
    'status' => getenv('DEPLOY_LOG_STATUS') ?: '',
    'release' => getenv('DEPLOY_LOG_RELEASE') ?: '',
    'branch' => getenv('DEPLOY_LOG_BRANCH') ?: '',
    'app_env' => getenv('DEPLOY_LOG_APP_ENV') ?: '',
    'commit' => getenv('DEPLOY_LOG_COMMIT') ?: '',
    'message' => getenv('DEPLOY_LOG_MESSAGE') ?: '',
    'user' => getenv('DEPLOY_LOG_USER') ?: 'unknown',
];

echo json_encode($entry, JSON_UNESCAPED_SLASHES), PHP_EOL;
PHP
}

cleanup_deployment_logs() {
  [[ -f "$DEPLOY_LOG_FILE" ]] || return

  local cutoff
  cutoff="$(date -u -d "$DEPLOY_LOG_RETENTION_DAYS days ago" '+%Y-%m-%dT%H:%M:%S' 2>/dev/null || true)"
  [[ -n "$cutoff" ]] || return

  local tmp_file
  tmp_file="$(mktemp)"

  DEPLOY_LOG_CUTOFF="$cutoff" "$PHP_BIN" "$DEPLOY_LOG_FILE" <<'PHP' >"$tmp_file"
<?php
$cutoff = strtotime((string) getenv('DEPLOY_LOG_CUTOFF'));
$file = $argv[1] ?? '';
$handle = fopen($file, 'rb');
if ($handle === false) {
    exit(0);
}

while (($line = fgets($handle)) !== false) {
    $entry = json_decode($line, true);
    if (!is_array($entry) || !isset($entry['timestamp'])) {
        echo $line;
        continue;
    }

    $timestamp = strtotime((string) $entry['timestamp']);
    if ($timestamp === false || $timestamp >= $cutoff) {
        echo $line;
    }
}
PHP

  mv "$tmp_file" "$DEPLOY_LOG_FILE"
}

clone_release() {
  local branch="$1"
  local release_dir="$2"

  git clone --depth=1 --branch "$branch" "$REPOSITORY" "$release_dir"

  if [[ -n "$DEPLOY_COMMIT" ]]; then
    local head_commit
    head_commit="$(git -C "$release_dir" rev-parse HEAD)"

    if [[ "$head_commit" != "$DEPLOY_COMMIT" ]]; then
      log "Branch $branch moved to $head_commit, checkout requested commit $DEPLOY_COMMIT"
      git -C "$release_dir" fetch --depth=1 origin "$DEPLOY_COMMIT"
      git -C "$release_dir" checkout --detach FETCH_HEAD
    fi
  fi
}

link_shared_paths() {
  local release_dir="$1"

  ln -sfn "$SHARED_DIR/.env.local" "$release_dir/.env.local"
  rm -rf "$release_dir/public_html/uploads" "$release_dir/var/log"
  mkdir -p "$release_dir/public_html" "$release_dir/var"
  ln -sfn "$SHARED_DIR/public_html/uploads" "$release_dir/public_html/uploads"
  ln -sfn "$SHARED_DIR/var/log" "$release_dir/var/log"
}

install_prebuilt_frontend() {
  local release_dir="$1"
  local commit="$2"
  local extract_dir="$release_dir/.frontend-extract"

  [[ -f "$FRONTEND_BUILD_ARCHIVE" ]] || fail "Frontend build archive not found: $FRONTEND_BUILD_ARCHIVE"

  log "Install prebuilt frontend from $FRONTEND_BUILD_ARCHIVE"
  rm -rf "$extract_dir"
  mkdir -p "$extract_dir"
  tar -xzf "$FRONTEND_BUILD_ARCHIVE" --no-same-owner -C "$extract_dir"

  [[ -f "$extract_dir/build/.vite/manifest.json" ]] || fail "Prebuilt frontend has no Vite manifest (build/.vite/manifest.json)"

  # The archive must be built from the same commit that is being deployed,
  # otherwise templates and asset hashes could disagree.
  local built_commit
  built_commit="$(tr -d '[:space:]' <"$extract_dir/BUILD_COMMIT" 2>/dev/null || true)"
  if [[ -n "$commit" && "$built_commit" != "$commit" ]]; then
    fail "Prebuilt frontend commit (${built_commit:-unknown}) does not match release commit ($commit)"
  fi

  rm -rf "$release_dir/public_html/build"
  mkdir -p "$release_dir/public_html"
  mv "$extract_dir/build" "$release_dir/public_html/build"
  rm -rf "$extract_dir"
}

build_frontend_on_server() {
  local release_dir="$1"

  log "No prebuilt frontend provided, build on server with $NPM_BIN"
  (cd "$release_dir" && "$NPM_BIN" ci)
  (cd "$release_dir" && "$NPM_BIN" run build)
}

install_release_dependencies() {
  local release_dir="$1"
  local commit="${2:-}"

  (cd "$release_dir" && "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader)

  if [[ -n "$FRONTEND_BUILD_ARCHIVE" ]]; then
    install_prebuilt_frontend "$release_dir" "$commit"
  else
    build_frontend_on_server "$release_dir"
  fi
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
  local tmp_link="${CURRENT_LINK}.tmp"

  ln -sfn "$release_dir" "$tmp_link"
  mv -Tf "$tmp_link" "$CURRENT_LINK"
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

cleanup_old_backups() {
  find "$DB_BACKUP_DIR" -type f -name '*.sql.gz' -mtime +"$BACKUP_RETENTION_DAYS" -delete 2>/dev/null || true
  find "$UPLOADS_BACKUP_DIR" -type f -name '*.tar.gz' -mtime +"$BACKUP_RETENTION_DAYS" -delete 2>/dev/null || true
}

load_shared_env() {
  set -a
  # shellcheck disable=SC1091
  source "$SHARED_DIR/.env.local"
  set +a
}

MYSQL_DEFAULTS_FILE=""
MYSQL_DB_NAME=""

# Разбирает DATABASE_URL (mysql://user:pass@host:port/db) и создаёт временный
# defaults-файл для клиентов mysql/mysqldump, чтобы пароль не попадал в argv.
prepare_mysql_client_from_database_url() {
  local database_url="${1:-${DATABASE_URL:-}}"

  [[ -n "$database_url" ]] || fail "DATABASE_URL is not set"

  cleanup_mysql_client_files

  MYSQL_DEFAULTS_FILE="$(mktemp)"
  chmod 600 "$MYSQL_DEFAULTS_FILE"

  local db_name_file
  db_name_file="$(mktemp)"

  DATABASE_URL_TO_PARSE="$database_url" DB_NAME_FILE="$db_name_file" "$PHP_BIN" <<'PHP' >"$MYSQL_DEFAULTS_FILE"
<?php
$url = getenv('DATABASE_URL_TO_PARSE') ?: '';
$parts = parse_url($url);
if ($parts === false || !in_array($parts['scheme'] ?? '', ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "DATABASE_URL must use mysql:// scheme\n");
    exit(1);
}
$quote = static fn (string $value): string => '"'.addcslashes($value, "\"\\").'"';
echo "[client]\n";
echo 'host=', $quote($parts['host'] ?? '127.0.0.1'), "\n";
echo 'port=', (int) ($parts['port'] ?? 3306), "\n";
echo 'user=', $quote(rawurldecode($parts['user'] ?? '')), "\n";
echo 'password=', $quote(rawurldecode($parts['pass'] ?? '')), "\n";
echo "default-character-set=utf8mb4\n";
file_put_contents((string) getenv('DB_NAME_FILE'), rawurldecode(ltrim($parts['path'] ?? '', '/')));
PHP

  MYSQL_DB_NAME="$(cat "$db_name_file")"
  rm -f "$db_name_file"

  [[ -n "$MYSQL_DB_NAME" ]] || fail "Database name is missing in DATABASE_URL"
}

cleanup_mysql_client_files() {
  if [[ -n "${MYSQL_DEFAULTS_FILE:-}" ]]; then
    rm -f "$MYSQL_DEFAULTS_FILE"
    MYSQL_DEFAULTS_FILE=""
  fi
}

mysql_cli() {
  mysql --defaults-extra-file="$MYSQL_DEFAULTS_FILE" "$@"
}

mysqldump_cli() {
  mysqldump --defaults-extra-file="$MYSQL_DEFAULTS_FILE" "$@"
}

backup_database() {
  local release_name="$1"

  if [[ -n "${DATABASE_BACKUP_COMMAND:-}" ]]; then
    log "Run custom database backup command"
    bash -lc "$DATABASE_BACKUP_COMMAND"
    return
  fi

  require_command mysqldump
  require_command gzip
  load_shared_env

  mkdir -p "$DB_BACKUP_DIR"

  local backup_file="$DB_BACKUP_DIR/${release_name}_database.sql.gz"
  prepare_mysql_client_from_database_url "$DATABASE_URL"

  mysqldump_cli --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 "$MYSQL_DB_NAME" | gzip -9 >"$backup_file"
  cleanup_mysql_client_files

  gzip -t "$backup_file"

  log "Database backup created: $backup_file"
}

backup_uploads() {
  local release_name="$1"
  local backup_file="$UPLOADS_BACKUP_DIR/${release_name}_uploads.tar.gz"

  mkdir -p "$UPLOADS_BACKUP_DIR"
  tar -czf "$backup_file" -C "$SHARED_DIR/public_html" uploads
  tar -tzf "$backup_file" >/dev/null
  log "Uploads backup created: $backup_file"
}

write_staging_success_marker() {
  local release_name="$1"
  local branch="$2"
  local commit="$3"

  mkdir -p "$DEPLOYMENTS_DIR"
  {
    printf 'STAGING_DEPLOYED_AT=%q\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    printf 'STAGING_RELEASE=%q\n' "$release_name"
    printf 'STAGING_BRANCH=%q\n' "$branch"
    printf 'STAGING_COMMIT=%q\n' "$commit"
  } >"$STAGING_MARKER_FILE"
}

require_recent_staging_marker() {
  [[ "${REQUIRE_STAGING_MARKER:-no}" == "yes" ]] || return
  [[ -f "$STAGING_MARKER_FILE" ]] || fail "Missing staging success marker: $STAGING_MARKER_FILE"

  local max_age_hours="${STAGING_MARKER_MAX_AGE_HOURS:-72}"
  local now
  local marker_mtime
  local marker_age_hours

  now="$(date +%s)"
  marker_mtime="$(stat -c %Y "$STAGING_MARKER_FILE")"
  marker_age_hours="$(((now - marker_mtime) / 3600))"

  if (( marker_age_hours > max_age_hours )); then
    fail "Staging marker is older than ${max_age_hours}h: $STAGING_MARKER_FILE"
  fi
}

list_release_candidates() {
  find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -print0 \
    | xargs -0 ls -dt 2>/dev/null || true
}

resolve_release_path() {
  local release="$1"

  if [[ -d "$release" ]]; then
    printf '%s\n' "$release"
    return
  fi

  if [[ -d "$RELEASES_DIR/$release" ]]; then
    printf '%s\n' "$RELEASES_DIR/$release"
    return
  fi

  fail "Release not found: $release"
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
  if [[ -z "$FRONTEND_BUILD_ARCHIVE" ]]; then
    require_command "$NPM_BIN"
  fi
  require_command tar
  require_command curl

  prepare_shared_layout
  acquire_deploy_lock
  trap release_deploy_lock EXIT

  release_name="$(date '+%Y-%m-%d_%H%M%S')"
  release_dir="$RELEASES_DIR/$release_name"
  previous_release="$(readlink "$CURRENT_LINK" 2>/dev/null || true)"

  log "Create release $release_name from $branch"
  append_deployment_log "deploy" "started" "$release_name" "$branch" "$app_env" "" "Deploy started"
  clone_release "$branch" "$release_dir"
  local commit
  commit="$(git -C "$release_dir" rev-parse HEAD 2>/dev/null || true)"
  link_shared_paths "$release_dir"
  install_release_dependencies "$release_dir" "$commit"

  if [[ "$with_backup" == "yes" ]]; then
    backup_database "$release_name"
    backup_uploads "$release_name"
    cleanup_old_backups
  fi

  run_release_console_tasks "$release_dir" "$app_env"
  switch_current "$release_dir"
  restart_services

  if ! run_health_check "$health_url"; then
    log "Health-check failed"
    append_deployment_log "deploy" "health_failed" "$release_name" "$branch" "$app_env" "$commit" "Health-check failed"
    if [[ -n "$previous_release" ]]; then
      log "Rollback to previous release: $previous_release"
      rollback_to "$previous_release"
      append_deployment_log "rollback" "succeeded" "$(basename "$previous_release")" "$branch" "$app_env" "" "Automatic rollback after failed health-check"
    fi
    exit 1
  fi

  if [[ "$app_env" == "staging" ]]; then
    write_staging_success_marker "$release_name" "$branch" "$commit"
  fi

  cleanup_old_releases
  cleanup_deployment_logs
  append_deployment_log "deploy" "succeeded" "$release_name" "$branch" "$app_env" "$commit" "Deploy finished"
  log "Deploy finished: $release_name"
}
