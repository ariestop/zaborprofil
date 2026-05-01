#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/deploy/common.sh
source "$SCRIPT_DIR/common.sh"

BRANCH="${BRANCH:-staging}"
APP_ENV="${APP_ENV:-staging}"
HEALTH_URL="${HEALTH_URL:-https://staging.zaborprofil.ru/health}"

deploy_release "$BRANCH" "$APP_ENV" "$HEALTH_URL" "no"
