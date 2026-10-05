#!/usr/bin/env bash
# Внешняя uptime-проверка: опрашивает URL из UPTIME_URLS (через пробел/перевод строки), ждёт HTTP 200
# и "status":"ok" в теле ответа (формат /health, /health/ready). При сбое выходит с кодом 1
# и, если заданы ALERT_TELEGRAM_BOT_TOKEN/ALERT_TELEGRAM_CHAT_ID, шлёт сообщение в Telegram.
#
# Переменные:
#   UPTIME_URLS            список URL (обязательно; пусто — проверка пропускается с кодом 0)
#   UPTIME_BASIC_AUTH      логин:пароль для закрытого Basic Auth стенда (staging)
#   UPTIME_ATTEMPTS        число попыток на URL (по умолчанию 3)
#   UPTIME_RETRY_DELAY     пауза между попытками, секунды (по умолчанию 5)
#   UPTIME_EXPECT_BODY     подстрока в теле ответа (по умолчанию "status":"ok"; пусто — только код 200)
#   ALERT_TELEGRAM_BOT_TOKEN, ALERT_TELEGRAM_CHAT_ID  необязательное оповещение
set -Eeuo pipefail

UPTIME_ATTEMPTS="${UPTIME_ATTEMPTS:-3}"
UPTIME_RETRY_DELAY="${UPTIME_RETRY_DELAY:-5}"
UPTIME_EXPECT_BODY="${UPTIME_EXPECT_BODY-\"status\":\"ok\"}"
UPTIME_URLS="${UPTIME_URLS:-}"

if [[ -z "${UPTIME_URLS//[[:space:]]/}" ]]; then
  printf 'UPTIME_URLS is empty, skip uptime check\n'
  exit 0
fi

failures=()

check_url() {
  local url="$1" attempt response status body
  local -a curl_args=(--silent --show-error --max-time 15 --write-out '\n%{http_code}')

  if [[ -n "${UPTIME_BASIC_AUTH:-}" ]]; then
    curl_args+=(--user "$UPTIME_BASIC_AUTH")
  fi

  for ((attempt = 1; attempt <= UPTIME_ATTEMPTS; attempt++)); do
    if response="$(curl "${curl_args[@]}" "$url" 2>/dev/null)"; then
      status="${response##*$'\n'}"
      body="${response%$'\n'*}"
      if [[ "$status" == "200" && ( -z "$UPTIME_EXPECT_BODY" || "$body" == *"$UPTIME_EXPECT_BODY"* ) ]]; then
        printf 'OK: %s\n' "$url"
        return 0
      fi
      printf 'attempt %d/%d: %s returned HTTP %s\n' "$attempt" "$UPTIME_ATTEMPTS" "$url" "$status" >&2
    else
      printf 'attempt %d/%d: %s is unreachable\n' "$attempt" "$UPTIME_ATTEMPTS" "$url" >&2
    fi

    if ((attempt < UPTIME_ATTEMPTS)); then
      sleep "$UPTIME_RETRY_DELAY"
    fi
  done

  return 1
}

notify_telegram() {
  if [[ -z "${ALERT_TELEGRAM_BOT_TOKEN:-}" || -z "${ALERT_TELEGRAM_CHAT_ID:-}" ]]; then
    return 0
  fi

  curl --silent --max-time 10 \
    --data-urlencode "chat_id=$ALERT_TELEGRAM_CHAT_ID" \
    --data-urlencode "text=$1" \
    "https://api.telegram.org/bot${ALERT_TELEGRAM_BOT_TOKEN}/sendMessage" >/dev/null || true
}

for url in $UPTIME_URLS; do
  if ! check_url "$url"; then
    failures+=("$url")
  fi
done

if ((${#failures[@]} > 0)); then
  message="zaborprofil uptime: недоступно или не здорово: ${failures[*]}"
  printf 'FAIL: %s\n' "$message" >&2
  notify_telegram "$message"
  exit 1
fi
