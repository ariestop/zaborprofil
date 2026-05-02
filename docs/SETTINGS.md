# Модуль Settings

Модуль `Settings` хранит системные настройки CMS в PostgreSQL и кэширует чтение
через Symfony Cache/Redis.

## Структура

- Таблица: `settings`.
- Уникальность: пара `scope + key`.
- Значение: JSONB поле `setting_value`.
- API: `/admin/api/settings`.
- Twig helper: `setting(scope, key, default)`.

## Примеры

Получить значение в Twig:

```twig
{{ setting('site', 'name', 'ЗаборПрофиль') }}
```

Обновить через Admin API:

```http
PUT /admin/api/settings/site/name
Content-Type: application/json
X-CSRF-Token: <token>

{
  "value": "ЗаборПрофиль",
  "description": "Название сайта"
}
```

## Правила именования

`scope` и `key` должны начинаться со строчной латинской буквы и содержать только:

- строчные латинские буквы;
- цифры;
- точку;
- нижнее подчеркивание;
- дефис.
