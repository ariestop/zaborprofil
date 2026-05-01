#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/deploy/common.sh
source "$SCRIPT_DIR/common.sh"

prepare_shared_layout

CURRENT_TARGET="$(readlink "$CURRENT_LINK" 2>/dev/null || true)"
TARGET_RELEASE="${1:-}"

if [[ -z "$TARGET_RELEASE" ]]; then
  TARGET_RELEASE="$(
    find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -print0 \
      | xargs -0 ls -dt 2>/dev/null \
      | grep -v -F "$CURRENT_TARGET" \
      | head -n 1
  )"
fi

[[ -n "$TARGET_RELEASE" ]] || fail "No previous release found."

log "Rollback to $TARGET_RELEASE"
rollback_to "$TARGET_RELEASE"

if [[ -n "${HEALTH_URL:-}" ]]; then
  run_health_check "$HEALTH_URL"
fi

log "Rollback finished"
