# E2E Scenario: Admin Stage 3 release-readiness

## Purpose

Проверить критичный путь новой админки после Этапа 3:

- вход в админку и shell загрузка;
- переход Pages -> Page Detail -> Builder;
- сохранение layout в builder;
- reorder блоков;
- обновление rich-text через bridge;
- получение preview-link.

## Preconditions

- Локальная среда поднята (`make up`).
- npm-зависимости и build корректны (`make npm-install`, `make npm-build`).
- Тестовая БД готова (`make test-db`).
- Есть администратор с правами `ROLE_ADMIN`/`ROLE_SUPER_ADMIN`.

## Steps

1. Открыть `/admin/login` и выполнить вход.
2. Убедиться, что загрузилась shell-страница с `#admin-app`.
3. Перейти в `/admin/pages` и убедиться, что таблица страниц отображается.
4. Создать/открыть страницу и перейти в `/admin/pages/{id}`.
5. Из detail перейти в `/admin/pages/{id}/builder`.
6. В Builder изменить layout (добавить/обновить контент).
7. Нажать save и дождаться подтверждения autosave.
8. Выполнить reorder блоков drag&drop.
9. В секции rich-text bridge обновить текст и сохранить.
10. Получить preview и открыть ссылку в новой вкладке.

## Expected result

- Все маршруты (`/admin/pages`, `/admin/pages/{id}`, `/admin/pages/{id}/builder`) рендерят shell без 404/500.
- Save/autosave builder возвращают успешный ответ.
- Reorder блоков отражается в API и повторной загрузке страницы.
- rich-text сохраняется в block payload и повторно загружается в редакторе.
- preview-link валиден и открывает preview-страницу.

## Regression signal

Сценарий считается проваленным, если:

- shell не загружается или уходит в redirect loop;
- builder canvas не инициализируется;
- dnd reorder не сохраняется;
- rich-text bridge не сохраняет изменения;
- preview-link не генерируется или возвращает 4xx/5xx.
