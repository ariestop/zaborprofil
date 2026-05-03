# Business Events

Business Events пишутся в Monolog channel `business` и файл:

```text
var/log/business.log
```

Сервис:

```php
App\Shared\Application\Logging\BusinessEventLogger
```

Текущие события:

- `page.published`;
- `page.pathChanged`;
- `setting.updated`;
- `setting.deleted`.

В следующих волнах сюда добавятся:

- `lead.created`;
- `lead.statusChanged`;
- `sitemap.regenerated`;
- `media.optimized`;
- `seo.auditCompleted`;
- `deploy.completed`;
- `rollback.completed`.
