# API layer админки

## Базовый контракт

Все запросы из admin frontend выполняются через единый клиент:

- `admin/shared/api/client.ts`

Клиент автоматически добавляет:

- `X-Requested-With: XMLHttpRequest`
- `X-Request-Id`
- `credentials: same-origin`
- CSRF header для mutating запросов (из meta-тегов shell layout)

## Ошибки

`ApiError` содержит:

- `status` (HTTP статус);
- `payload` (тело ответа);
- `code` (нормализованный enum-подобный код: `UNAUTHORIZED`, `FORBIDDEN`, `NOT_FOUND`, `VALIDATION_ERROR`, `SERVER_ERROR`, `UNKNOWN`).

## Query foundation

Используется `@tanstack/react-query`:

- общий `QueryClient` в `app/providers/query-client.ts`;
- провайдер в `app/providers/AppProviders.tsx`;
- ключи и helper `queryOptions` в `shared/api/query.ts`.

## Доменные API hooks (Этап 2)

Вынесены отдельные API-слои по сущностям:

- `entities/page/api.ts` — pages list/detail/update, preview, revisions, block upsert/reorder;
- `entities/lead/api.ts` — CRM leads list и статусные мутации;
- `entities/user/api.ts` — список админ-пользователей и обновление ролей;
- `entities/media/api.ts` и `entities/seo/api.ts` — базовые query hooks.

## Validation mapping

Для RHF-форм добавлен helper:

- `shared/api/validation.ts` — маппинг backend validation payload (`422`) в `setError`.

## Правила использования

- Никаких прямых `fetch` в route-level страницах без уважительной причины.
- Любые повторяемые query/mutation должны иметь общий helper и query key.
- Ошибки 401/403/404/422/500 обрабатываются через единый тип `ApiError`.
- Server validation ошибки должны прокидываться в RHF через `applyServerValidationErrors`.
