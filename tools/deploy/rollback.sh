#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/deploy/common.sh
source "$SCRIPT_DIR/common.sh"

prepare_shared_layout
require_command "$PHP_BIN"

CURRENT_TARGET="$(readlink "$CURRENT_LINK" 2>/dev/null || true)"
TARGET_RELEASE="${1:-}"

if [[ "$TARGET_RELEASE" == "--list" ]]; then
  list_release_candidates
  exit 0
fi

acquire_deploy_lock
trap release_deploy_lock EXIT

if [[ -z "$TARGET_RELEASE" ]]; then
  while IFS= read -r release; do
    if [[ "$release" != "$CURRENT_TARGET" ]]; then
      TARGET_RELEASE="$release"
      break
    fi
  done < <(list_release_candidates)
else
  TARGET_RELEASE="$(resolve_release_path "$TARGET_RELEASE")"
fi

[[ -n "$TARGET_RELEASE" ]] || fail "No previous release found."

log "Rollback to $TARGET_RELEASE"
append_deployment_log "rollback" "started" "$(basename "$TARGET_RELEASE")" "" "" "" "Manual rollback started"
rollback_to "$TARGET_RELEASE"

if [[ -n "${HEALTH_URL:-}" ]]; then
  run_health_check "$HEALTH_URL"
fi

append_deployment_log "rollback" "succeeded" "$(basename "$TARGET_RELEASE")" "" "" "" "Manual rollback finished"
log "Rollback finished"
