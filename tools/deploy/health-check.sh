#!/usr/bin/env bash

set -Eeuo pipefail

URL="${1:-${HEALTH_URL:-https://zaborprofil.ru/health}}"
RETRIES="${HEALTH_RETRIES:-10}"
SLEEP_SECONDS="${HEALTH_SLEEP_SECONDS:-3}"

for attempt in $(seq 1 "$RETRIES"); do
  if response="$(curl -fsS --max-time 10 "$URL" 2>/dev/null)" && grep -q '"status":"ok"' <<<"$response"; then
    printf '%s\n' "$response"
    exit 0
  fi

  printf 'Health-check failed for %s, attempt %s/%s\n' "$URL" "$attempt" "$RETRIES" >&2
  sleep "$SLEEP_SECONDS"
done

exit 1
