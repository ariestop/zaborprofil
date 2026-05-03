#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/deploy/common.sh
source "$SCRIPT_DIR/common.sh"

APP_ENV="${APP_ENV:-staging}"
HEALTH_URL="${HEALTH_URL:-https://staging.zaborprofil.ru/health}"
BASE_URL="${BASE_URL:-${HEALTH_URL%/health}}"
READY_URL="${READY_URL:-$BASE_URL/health/ready}"

curl_args=(-fsS --max-time "${SMOKE_CURL_TIMEOUT:-10}")
if [[ -n "${BASIC_AUTH:-}" ]]; then
  curl_args+=(-u "$BASIC_AUTH")
fi

check_http() {
  local label="$1"
  local url="$2"

  log "Smoke HTTP check: $label $url"
  curl "${curl_args[@]}" "$url" >/dev/null
}

require_command curl
require_command "$PHP_BIN"

run_health_check "$HEALTH_URL"
check_http "ready" "$READY_URL"
check_http "sitemap" "$BASE_URL/sitemap.xml"
check_http "robots" "$BASE_URL/robots.txt"
check_http "admin login" "$BASE_URL/admin/login"

if [[ ! -d "$CURRENT_LINK" ]]; then
  fail "Current release is missing: $CURRENT_LINK"
fi

log "Run Symfony smoke command on current release"
(cd "$CURRENT_LINK" && "$PHP_BIN" bin/console app:smoke:test --env="$APP_ENV" --no-interaction)

log "Staging smoke checks passed"
