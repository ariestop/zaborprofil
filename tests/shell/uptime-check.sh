#!/usr/bin/env bash
# Проверяет tools/monitoring/uptime-check.sh против локального HTTP-сервера.
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCRIPT="$ROOT/tools/monitoring/uptime-check.sh"
WORK="$(mktemp -d)"
SERVER_PID=""

cleanup() {
  if [[ -n "$SERVER_PID" ]]; then
    kill "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$WORK"
}
trap cleanup EXIT

printf '{"status":"ok"}' >"$WORK/ok.json"
printf '{"status":"error"}' >"$WORK/bad.json"

PORT="$(python3 -c 'import socket; s = socket.socket(); s.bind(("127.0.0.1", 0)); print(s.getsockname()[1]); s.close()')"
(cd "$WORK" && python3 -m http.server "$PORT" --bind 127.0.0.1 >/dev/null 2>&1) &
SERVER_PID=$!

for _ in $(seq 1 50); do
  if curl --silent --fail "http://127.0.0.1:$PORT/ok.json" >/dev/null 2>&1; then
    break
  fi
  sleep 0.1
done

BASE="http://127.0.0.1:$PORT"
export UPTIME_ATTEMPTS=2 UPTIME_RETRY_DELAY=0

fail() {
  printf 'FAIL: %s\n' "$1" >&2
  exit 1
}

UPTIME_URLS="" bash "$SCRIPT" >/dev/null || fail 'empty UPTIME_URLS must be skipped'
UPTIME_URLS="$BASE/ok.json" bash "$SCRIPT" >/dev/null || fail 'healthy endpoint must pass'
UPTIME_URLS="$BASE/ok.json $BASE/ok.json" bash "$SCRIPT" >/dev/null || fail 'several healthy endpoints must pass'

if UPTIME_URLS="$BASE/bad.json" bash "$SCRIPT" >/dev/null 2>&1; then
  fail 'non-ok body must fail'
fi
if UPTIME_URLS="$BASE/missing.json" bash "$SCRIPT" >/dev/null 2>&1; then
  fail 'HTTP 404 must fail'
fi
if UPTIME_URLS="$BASE/ok.json $BASE/missing.json" bash "$SCRIPT" >/dev/null 2>&1; then
  fail 'one failing endpoint must fail the whole check'
fi
if UPTIME_URLS="http://127.0.0.1:1/health" bash "$SCRIPT" >/dev/null 2>&1; then
  fail 'unreachable endpoint must fail'
fi
UPTIME_EXPECT_BODY="" UPTIME_URLS="$BASE/bad.json" bash "$SCRIPT" >/dev/null || fail 'empty UPTIME_EXPECT_BODY must only check HTTP 200'

printf 'uptime-check tests passed\n'
