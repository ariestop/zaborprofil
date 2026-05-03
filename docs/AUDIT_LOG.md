# Admin Audit Log

Audit Log фиксирует изменения важных сущностей админки:

- `Page`;
- `PageBlock`;
- `Setting`;
- `Redirect`.

Для каждой записи сохраняется:

- время;
- actor id/email;
- IP;
- user agent;
- request id;
- действие (`create`, `update`);
- тип и id сущности;
- old values;
- new values.

Раздел админки:

```text
/admin/system/audit
```

API:

```text
GET /admin/api/system/audit
```

Сейчас audit log пишется синхронно в том же flush через Doctrine `onFlush`.
Если нагрузка вырастет, запись можно перенести в Messenger, сохранив текущую
структуру таблицы.
