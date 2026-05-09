# Content Builder API

## Базовый префикс

`/admin/api/content/pages/{id}/builder*`

Все endpoints доступны только для авторизованных администраторов (`ROLE_ADMIN`
firewall) и дополнительно проверяют `AdminPermission`.

## Endpoints

### GET `/admin/api/content/pages/{id}/builder`

Возвращает текущий structured-документ страницы:

```json
{
  "pageId": "01J...",
  "updatedAt": "2026-05-09T12:00:00+00:00",
  "blocks": []
}
```

### PUT `/admin/api/content/pages/{id}/builder`

Синхронизирует блоки страницы по полному списку из `blocks`.

Тело запроса:

```json
{
  "blocks": [
    {
      "id": "01J...",
      "type": "hero.classic",
      "enabled": true,
      "position": 0,
      "content": {},
      "settings": {},
      "metadata": {
        "createdAt": "2026-05-09T00:00:00+00:00",
        "updatedAt": "2026-05-09T00:00:00+00:00"
      }
    }
  ]
}
```

Поведение:

- неизвестный `type` -> `422 VALIDATION`;
- невалидные поля (`enabled`, `content/settings`) -> `422 VALIDATION`;
- блоки, отсутствующие в новом списке, удаляются;
- порядок блоков нормализуется по индексу в списке.

### POST `/admin/api/content/pages/{id}/builder/preview`

Принимает `blocks` в том же формате, возвращает HTML-предпросмотр:

```json
{
  "html": "<section>...</section>"
}
```

### POST `/admin/api/content/pages/{id}/builder/publish`

Публикует страницу через существующий publish-flow (`PageRevision`,
`PagePublication`, SEO pre-publish checklist).

## Ошибки

Формат ошибок общий для Content API:

```json
{
  "error": "Validation message",
  "code": "VALIDATION"
}
```
