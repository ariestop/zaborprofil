# 50. Workflow публикации страниц и планировщик

Документ описывает жизненный цикл страницы (`draft → review → approved → published / scheduled`), права на переходы,
отложенную публикацию и снятие по расписанию, журнал событий, сравнение ревизий и откат.

## Статусы и переходы

| Из статуса | Допустимые следующие статусы |
| --- | --- |
| `draft` | `review`, `approved`, `published`, `deleted` |
| `review` | `approved`, `draft`, `published`, `deleted` |
| `approved` | `published`, `scheduled`, `draft`, `deleted` |
| `scheduled` | `published`, `approved` (отмена расписания), `draft`, `deleted` |
| `published` | `unpublished`, `scheduled` (плановое снятие), `archived`, `deleted` |
| `unpublished` | `draft`, `published`, `archived`, `deleted` |
| `archived` | `draft`, `deleted` |
| `deleted` | `draft` |

Матрица переходов — `PageStatusTransitionPolicy` (домен). Права проверяет `PageWorkflowVoter`
(атрибут `page.workflow.transition`, subject — `PageTransition`) через `PageWorkflowGuard`. Проверка выполняется в
обработчиках (use case), а не только в контроллере, поэтому её нельзя обойти из консоли или другого входа.

| Целевой статус | Право (`AdminPermission`) |
| --- | --- |
| `review`, `draft` (из `review`/`published` и др.) | `PAGES_SUBMIT_REVIEW` |
| `approved` | `PAGES_APPROVE` |
| `approved` из `scheduled` (отмена расписания), `scheduled` | `PAGES_SCHEDULE` |
| `published` | `PAGES_PUBLISH` |
| `unpublished`, `draft` из `unpublished` | `PAGES_UNPUBLISH` |
| `archived`, `draft` из `archived` | `PAGES_ARCHIVE` |
| `deleted`, `draft` из `deleted` | `PAGES_DELETE` |

Ошибки API имеют единый формат `{ "error": "...", "code": "..." }`: `403 ACCESS_DENIED` — нет права на переход,
`422 VALIDATION` — недопустимый переход, дата в прошлом, не пройден SEO pre-publish checklist и т. п.

## Admin API

| Метод и путь | Назначение |
| --- | --- |
| `PATCH /admin/api/content/pages/{id}/status` | смена статуса; поля `status`, `comment`, для `scheduled` — `publishAt`, `unpublishAt` |
| `POST /admin/api/content/pages/{id}/publish` | публикация (`comment` необязателен) |
| `POST /admin/api/content/pages/{id}/schedule` | `{publishAt, unpublishAt, comment}`; `approved`/`scheduled` — публикация в `publishAt` (опционально снятие в `unpublishAt`), `published` — только плановое снятие |
| `DELETE /admin/api/content/pages/{id}/schedule` | отмена расписания: `scheduled → approved`, у `published` снимается плановое снятие |
| `GET /admin/api/content/pages/{id}/workflow` | состояние: статус, даты, `hasUnpublishedChanges`, опубликованная и запланированная ревизии, переходы с флагом `allowed`, журнал |
| `GET /admin/api/content/pages/{id}/revisions/diff?from=<id>&to=<id\|current>` | diff двух ревизий или ревизии и рабочей версии |
| `POST /admin/api/content/pages/{id}/revisions/{revisionId}/rollback` | откат рабочей версии к ревизии |

Даты принимаются в ISO 8601 (с часовым поясом) и приводятся к часовому поясу приложения.
При постановке в расписание (`approved → scheduled`) выполняется SEO pre-publish checklist и сохраняется снимок
(`PageRevision`, `scheduledRevision` в `PagePublication`). В момент публикации чек-лист выполняется ещё раз.

## Планировщик

Консольная команда:

```bash
php bin/console app:content:publish-scheduled [--dry-run] [--limit=100] --env=prod
```

- Публикует страницы `scheduled`, у которых `scheduledPublishAt <= now`, и снимает `published`-страницы, у которых
  `scheduledUnpublishAt <= now`.
- Идемпотентна: страница выбирается по статусу и дате, после обработки статус меняется, поэтому повторный запуск ничего не делает.
- Параллельные запуски исключены блокировкой `flock` на `var/lock/publish-scheduled.lock`; второй процесс пишет `skipping` и завершается с кодом 0.
- Страница, не прошедшая чек-лист публикации (или с уже пропущенным окном публикации), возвращается в `approved`, причина
  пишется в журнал событием `schedule_failed`; повторов нет, команда завершается с кодом 1 (cron/systemd отметит сбой).
- `--dry-run` только показывает, что было бы сделано. `--limit` (1–1000) ограничивает число страниц на направление за запуск.
- После публикации/снятия сбрасывается публичный кэш страницы (`PublicPageCacheInvalidator`), в журнале аудита создаётся запись
  `page.workflow.scheduled_published` / `page.workflow.scheduled_unpublished` с пользователем `system`.

Индексы `(status, scheduled_publish_at)` и `(status, scheduled_unpublish_at)` на `content_pages` делают выборку дешёвой
даже при большом числе страниц (миграция `Version20261010090000`).

### Запуск по расписанию: Beget (cron)

В панели Beget -> «Cron» добавить задачу, выполняемую каждую минуту (пути — как в [49-beget-staging-deploy](49-beget-staging-deploy.md)):

```cron
* * * * * cd $HOME/dev.zaborprofil.ru && /usr/local/bin/php8.5 bin/console app:content:publish-scheduled --env=staging --no-interaction >> var/log/publish-scheduled.log 2>&1
```

Для production заменить каталог и `--env=prod`. Если Beget не разрешает запуск раз в минуту, точность публикации
ограничена периодом cron (например, 5 минут) — это нужно учитывать при выборе времени.

### Запуск по расписанию: VPS (systemd timer)

Шаблоны `tools/deploy/templates/zaborprofil-publish-scheduled.service` и `.timer`:

```bash
sudo cp tools/deploy/templates/zaborprofil-publish-scheduled.{service,timer} /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now zaborprofil-publish-scheduled.timer
systemctl list-timers zaborprofil-publish-scheduled.timer
journalctl -u zaborprofil-publish-scheduled.service -n 50
```

Пути, пользователь и версия PHP в unit подгоняются под сервер так же, как в других шаблонах (`zaborprofil-messenger.service`).

### Мониторинг

Проверка `scheduled_publishing` в `/health` (не влияет на readiness) возвращает `warning`, если есть запланированная публикация
или снятие, просроченные более чем на 10 минут, — признак того, что cron/timer не запускается.

## Журнал событий

Каждое изменение жизненного цикла пишется в журнал аудита (`AuditLogEntry`, `entityType = Page`):
`page.workflow.status_changed`, `published`, `unpublished`, `scheduled`, `schedule_cancelled`, `scheduled_published`,
`scheduled_unpublished`, `schedule_failed`, `rolled_back`, `archived`. В записи — пользователь (`system` для планировщика),
переход статусов, комментарий и детали (ревизия, даты). История страницы отдаётся в `GET .../workflow` и показывается в админке.
Порт `PageWorkflowJournalInterface` изолирует модуль Content от AuditLog.

## Сравнение ревизий и откат

`GET .../revisions/diff` возвращает:

- `fields` — заголовок, H1, slug, path, тип, шаблон (для текста — пословный `textDiff` из сегментов `equal`/`insert`/`delete`);
- `seo` и `settings` — поля SEO и настройки страницы;
- `blocks` — добавленные, удалённые и изменённые блоки (сопоставление по id, затем по типу и названию, затем по типу и позиции;
  перестановка отдаётся как изменение `order`);
- `summary` — счётчики, `hasChanges` — есть ли различия.

`to=current` сравнивает с рабочей версией страницы. Флаг `hasUnpublishedChanges` в `workflow` показывает, что рабочая версия
отличается от опубликованной ревизии. Откат создаёт новую ревизию с автором-пользователем и событием `rolled_back` в журнале.

## Интерфейс админки

Код — `admin/features/publishing/`:

- `PagePublishingPanel` — статус, плашка «Есть неопубликованные изменения», расписание, кнопки всех допустимых переходов
  (недоступные по правам отключены), комментарий к изменению и журнал событий;
- `ScheduleDialog` — выбор даты публикации и снятия (локальное время браузера, в API уходит ISO UTC);
- `RevisionHistory` и `RevisionDiffView` — список ревизий, diff с текущей версией и (опционально) откат с подтверждением.

Подключение к единому экрану страницы `/admin/pages/:id` — через слоты `admin/features/page-editor/slots.tsx`:

- `PagePublishingSlot` (шапка редактора) — кнопка «Публикация и расписание». Перед открытием сохраняет несохранённые правки редактора
  (`saveAll`; при ошибке сохранения панель не открывается), затем показывает `PagePublishingPanel` в диалоге. После смены статуса или
  расписания обновляются данные страницы и список страниц; при смене статуса снаружи (например, кнопкой «Опубликовать» в шапке) запрос workflow перечитывается;
- `PageRevisionsSlot` (вкладка «Ревизии») — `RevisionHistory` в режиме «только сравнение» (`allowRollback={false}`): откат остаётся в списке
  «История версий» вкладки и использует тот же обработчик отката, поэтому проходит те же проверки прав, запись в журнал и создаёт новую ревизию.

Кнопки «Опубликовать» в шапке и «Удалить» остаются основными действиями редактора, остальные переходы вынесены в панель.

## Тесты

- Unit: `tests/Unit/Content/` (`PageSchedulingTest`, `PageWorkflowVoterTest`, `TextDifferTest`, `PageRevisionDifferTest`).
- Functional (MySQL 8.4): `tests/Functional/Content/PageWorkflowApiTest.php`, `ScheduledPublishingCommandTest.php`.
- Frontend: `admin/features/publishing/*.spec.ts(x)` (vitest).

## Связанные документы

- [34-deployment](34-deployment.md)
- [49-beget-staging-deploy](49-beget-staging-deploy.md)
- [CONTENT_EDITOR_GUIDE](CONTENT_EDITOR_GUIDE.md)
- [29-healthchecks](29-healthchecks.md)
