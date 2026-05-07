# 14. API area

В проекте две категории API:

1. **Admin API** — `^/admin/api/...`, JSON, под admin firewall, CSRF + Origin (см. [12-admin-area](12-admin-area.md)). Реализован.
2. **Public API** — JSON endpoints вне `/admin`. Частично реализован (`POST /api/leads`), версионированный внешний API `/api/v1/...` остаётся целевым.

## Admin API: текущее состояние

См. [CONTENT_ENGINE.md](CONTENT_ENGINE.md) для конкретных контрактов Content. Settings и Seo имеют свои admin API под `/admin/api/settings/...` и `/admin/api/seo/...`.

Общие правила:

- JSON-в JSON-out.
- Парсинг JSON: `JsonRequest::parse(...)` → DTO с Validator constraints.
- Ответ: `ContentApiResponder::success(...)` / `::error(...)`.
- Ошибки 500 заменяются на generic с логированием.
- Каждый небезопасный метод требует `X-CSRF-Token`.

## Public API: целевые правила

### Фактический публичный endpoint

`POST /api/leads`:

- принимает lead payload (`source`, `name`, `phone`, `email`, `message`, `consent`, `consentText`, `pageUrl`, `policyUrl`, `formLoadedAt`, honeypot `website`);
- normal lead: `201` и статус `new`;
- spam lead: нейтральный `202`, статус `spam`, `spamScore`/`spamReasons`;
- антиспам: honeypot, минимальное время заполнения, IP rate limit, link scoring;
- уведомления: email и Telegram (если заданы env переменные).

### Версионирование

- Префикс: `/api/v1/...`.
- Breaking changes — новая мажорная версия.
- Минорные/патч — backward compatible.

### Аутентификация

| Способ | Когда |
|---|---|
| Anonymous | публичный read-only (например, sitemap-data) |
| Bearer token (long-lived) | M2M-интеграции |
| OAuth2 / JWT | целевое для B2C/B2B кабинетов |

### Авторизация

- Через Symfony Security firewall `api`.
- Voter’ы по ресурсам.
- Никаких `ROLE_ADMIN` на public API — только специфичные `ROLE_API_*`.

### CORS

- Allow-list origin (не `*`).
- `Access-Control-Allow-Credentials: false` для anonymous эндпоинтов.
- Контролируется на уровне nginx или kernel.response listener.

### Rate limiting

`symfony/rate-limiter` уже в зависимостях. Лимиты:

- Anonymous: 60 req/min на IP.
- Authenticated: 600 req/min на токен.
- Login endpoint: 10 req/min.
- Lead submission: 5 req/min на IP.

### Идемпотентность

POST-эндпоинты для side-effect операций должны принимать `Idempotency-Key` header. Сервер кеширует ответ на ключ + endpoint в Redis на 24ч.

### Ошибки

JSON-формат:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Invalid request",
    "details": {
      "title": ["This value should not be blank."]
    },
    "request_id": "01HZAB..."
  }
}
```

| HTTP | Когда |
|---|---|
| 400 | malformed JSON |
| 401 | нет/невалидный токен |
| 403 | нет прав |
| 404 | ресурс не найден |
| 409 | конфликт (duplicate path) |
| 422 | validation error |
| 429 | rate limit |
| 500 | неожиданный fail (стек не отдаём) |
| 503 | downstream недоступен |

### Sеrialization

- Никогда не сериализовать Doctrine entity напрямую (lazy loading, mapping leak).
- Всегда — через output DTO + Symfony Serializer / ручной mapper.
- Поля snake_case или camelCase — единая конвенция (рекомендуется `camelCase`).

### Webhook receivers (целевое)

- Префикс `/webhooks/<source>`.
- Без firewall, но с HMAC verification.
- Идемпотентность по headers.
- Лог канал `business`/`integration`.

## Документирование API

Целевое:

- OpenAPI спецификация (`docs/openapi.yaml` или генератор `nelmio/api-doc-bundle`).
- Включить в CI lint + diff на breaking changes.

## Чек-лист API endpoint

- [ ] Версия в URL (`/api/v1/...`).
- [ ] Аутентификация явно определена.
- [ ] Авторизация через voter.
- [ ] Rate limit настроен.
- [ ] DTO + Validator на входе.
- [ ] Output DTO без leak’ов Doctrine.
- [ ] Idempotency-Key для POST с side effects.
- [ ] CORS правила проверены.
- [ ] Ошибки в едином JSON-формате.
- [ ] OpenAPI/документация обновлена.
- [ ] Functional-тест.

## Связанные документы

- [12-admin-area](12-admin-area.md)
- [20-security-and-access-control](20-security-and-access-control.md)
- [30-error-handling](30-error-handling.md)
- [28-logging-observability](28-logging-observability.md)
