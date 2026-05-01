#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/deploy/common.sh
source "$SCRIPT_DIR/common.sh"

if [[ "${CONFIRM_STAGING_DEPLOYED:-no}" != "yes" ]]; then
  fail "Production deploy requires CONFIRM_STAGING_DEPLOYED=yes after successful staging deploy."
fi

BRANCH="${BRANCH:-master}"
APP_ENV="${APP_ENV:-prod}"
HEALTH_URL="${HEALTH_URL:-https://zaborprofil.ru/health}"

# Production worker installed from
# tools/deploy/templates/zaborprofil-messenger.service.
export WORKER_SERVICE="${WORKER_SERVICE:-zaborprofil-messenger}"

deploy_release "$BRANCH" "$APP_ENV" "$HEALTH_URL" "yes"
