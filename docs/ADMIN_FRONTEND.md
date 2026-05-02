# Vue-админка

Админка остается защищенной Symfony Security, а пользовательский интерфейс
загружается как Vue 3 приложение внутри Twig-шаблона `templates/admin/dashboard.html.twig`.

## Маршрутизация

- `/admin/login` остается серверной Twig-страницей.
- `/admin`, `/admin/dashboard`, `/admin/settings`, `/admin/seo/redirects` рендерят один SPA shell.
- `/admin/api/*` остается JSON API и защищается CSRF subscriber.

На первом этапе не добавляются внешние frontend-зависимости вроде `vue-router`
или Pinia. В `assets/admin/router/index.ts` реализован минимальный history-router,
а `assets/admin/stores/auth.ts` хранит состояние текущего администратора.

## API-клиент

`assets/admin/api/client.ts` автоматически добавляет:

- `X-CSRF-Token` для state-changing запросов;
- `X-Requested-With: XMLHttpRequest`;
- `X-Request-Id`;
- `credentials: same-origin`.

CSRF-токен передается из Twig через meta-теги.
