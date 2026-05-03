#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/deploy/common.sh
source "$SCRIPT_DIR/common.sh"

APP_ENV="${APP_ENV:-prod}"
HEALTH_URL="${HEALTH_URL:-https://zaborprofil.ru/health}"
READY_URL="${READY_URL:-${HEALTH_URL%/health}/health/ready}"
DISK_USAGE_CRITICAL_PERCENT="${DISK_USAGE_CRITICAL_PERCENT:-90}"
MONITOR_SERVICES="${MONITOR_SERVICES:-$NGINX_SERVICE $PHP_FPM_SERVICE $WORKER_SERVICE ${POSTGRES_SERVICE:-postgresql} ${REDIS_SERVICE:-redis-server}}"

failures=()

record_failure() {
  failures+=("$1")
  printf 'FAIL: %s\n' "$1" >&2
}

notify_alerts() {
  local text="$1"

  if [[ -n "${ALERT_WEBHOOK_URL:-}" ]]; then
    curl -fsS --max-time 10 \
      -H 'Content-Type: application/json' \
      --data "$(printf '{"text":%s}' "$("$PHP_BIN" -r 'echo json_encode($argv[1], JSON_UNESCAPED_SLASHES);' "$text")")" \
      "$ALERT_WEBHOOK_URL" >/dev/null || true
  fi

  if [[ -n "${ALERT_TELEGRAM_BOT_TOKEN:-}" && -n "${ALERT_TELEGRAM_CHAT_ID:-}" ]]; then
    curl -fsS --max-time 10 \
      --data-urlencode "chat_id=$ALERT_TELEGRAM_CHAT_ID" \
      --data-urlencode "text=$text" \
      "https://api.telegram.org/bot${ALERT_TELEGRAM_BOT_TOKEN}/sendMessage" >/dev/null || true
  fi
}

check_http_json_status() {
  local label="$1"
  local url="$2"
  local response

  if ! response="$(curl -fsS --max-time 10 "$url" 2>/dev/null)"; then
    record_failure "$label is not reachable: $url"
    return
  fi

  if ! grep -q '"status":"ok"' <<<"$response"; then
    record_failure "$label returned non-ok status: $url"
  fi
}

check_disk() {
  local usage

  usage="$(df -P "$APP_ROOT" | awk 'NR == 2 {gsub("%", "", $5); print $5}')"
  if [[ -z "$usage" ]]; then
    record_failure "disk usage check failed for $APP_ROOT"
    return
  fi

  if (( usage >= DISK_USAGE_CRITICAL_PERCENT )); then
    record_failure "disk usage is ${usage}% on $APP_ROOT"
  fi
}

check_services() {
  local service

  if ! command -v systemctl >/dev/null 2>&1; then
    log "systemctl is not available, skip service checks"
    return
  fi

  for service in $MONITOR_SERVICES; do
    if ! systemctl is-active --quiet "$service"; then
      record_failure "systemd service is not active: $service"
    fi
  done
}

check_database() {
  if [[ ! -f "$SHARED_DIR/.env.local" ]]; then
    record_failure "shared env is missing: $SHARED_DIR/.env.local"
    return
  fi

  load_shared_env
  export_pg_env_from_database_url "$DATABASE_URL"

  if ! psql --tuples-only --no-align --command 'select 1;' >/dev/null 2>&1; then
    record_failure "PostgreSQL check failed"
  fi
}

check_redis() {
  if [[ -z "${REDIS_URL:-}" ]]; then
    log "REDIS_URL is not set, skip Redis ping"
    return
  fi

  if command -v redis-cli >/dev/null 2>&1; then
    if ! redis-cli -u "$REDIS_URL" ping 2>/dev/null | grep -q '^PONG$'; then
      record_failure "Redis ping failed"
    fi
  else
    log "redis-cli is not available, skip Redis ping"
  fi
}

check_console_smoke() {
  if [[ ! -d "$CURRENT_LINK" ]]; then
    record_failure "current release is missing: $CURRENT_LINK"
    return
  fi

  if ! (cd "$CURRENT_LINK" && "$PHP_BIN" bin/console app:smoke:test --env="$APP_ENV" --no-interaction >/dev/null); then
    record_failure "Symfony smoke command failed"
  fi
}

require_command curl
require_command "$PHP_BIN"
require_command psql

check_http_json_status "health" "$HEALTH_URL"
check_http_json_status "readiness" "$READY_URL"
check_disk
check_services
check_database
check_redis
check_console_smoke

if (( ${#failures[@]} > 0 )); then
  alert_text="zaborprofil monitoring check failed on $(hostname): $(printf '%s; ' "${failures[@]}")"
  notify_alerts "$alert_text"
  exit 1
fi

log "Monitoring checks passed"
