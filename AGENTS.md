# Zaborprofil — документация для агентов (AI / Cursor)

Краткий контекст проекта для ассистентов и автоматизированных агентов.

## Стек и роли

| Часть | Технологии | Назначение |
|-------|------------|------------|
| **Frontend** | Next.js 16 (App Router), React 19 | UI, страницы, каталог, калькулятор, корзина |
| **CMS** | Payload CMS 3 (в Next.js) | Контент: страницы, блоки, медиа, навигация, статьи |
| **Backend API** | Symfony 8, PHP 8.4 | Каталог, расчёт, сметы, заказы, лиды, чат, PDF |
| **БД** | MySQL 8 (Symfony), SQLite (Payload) | Бизнес-данные и данные CMS |
| **Админка каталога** | EasyAdmin 5 (Symfony) | Категории, товары, варианты товаров |

## Структура репозитория

```
zaborprofil/
├── nextjs/                    # Next.js + Payload
│   ├── app/                   # App Router: страницы, (payload)/admin, api
│   ├── blocks/                # Блоки Payload (Hero, Text, FAQ, CTA, Grid)
│   ├── collections/           # Коллекции Payload (Pages, Users, Media, …)
│   ├── lib/api/client.ts      # Клиент к Symfony API (api.getCatalog, api.calculate, …)
│   ├── payload.config.ts      # Конфиг Payload
│   └── payload.db             # SQLite БД Payload (не коммитить секреты)
├── symfony/                   # Symfony API
│   ├── config/                # Конфиги, routes (в т.ч. easyadmin, framework)
│   ├── migrations/            # Doctrine-миграции
│   ├── public/                # Document root: index.php, .htaccess
│   └── src/
│       ├── Catalog/           # Каталог: Entity (Category, Product, ProductVariant), UseCase, Repository
│       ├── Controller/
│       │   ├── Admin/          # EasyAdmin: DashboardController, *CrudController
│       │   └── Api/            # REST: Catalog, Calculate, Estimate, Order, Lead, Chat, Document
│       ├── Configurator/       # Движок расчёта
│       ├── Estimation/         # Сметы
│       ├── Orders/             # Заказы
│       ├── Pricing/            # Цены, правила
│       └── ...
├── docker/                    # Dockerfile для nextjs и symfony
├── docker-compose.yml
├── AGENTS.md                  # Этот файл
└── .cursor/rules/             # Правила Cursor (.mdc)
```

## Адреса и окружение

- **Сайт (Symfony в OSPanel):** например `http://zaborprofil/` — document root: `symfony/public`, обязателен `.htaccess` и mod_rewrite.
- **Админка каталога (EasyAdmin):** `http://zaborprofil/admin` — редирект на список категорий, меню: Категории, Товары, Варианты товаров.
- **API Symfony:** те же хост + `/api/...` (catalog, product, calculate, estimate, order, lead, chat, documents).
- **Next.js (локально):** `http://localhost:3000`, Payload Admin: `http://localhost:3000/admin`. API бэкенда задаётся через `NEXT_PUBLIC_API_URL` (по умолчанию `http://localhost:8080`).

Переменные окружения: `symfony/.env`, `nextjs/.env` / `.env.local`; в репозитории только примеры (`.env.example`).

## API (Symfony)

Базовый URL API — корень Symfony (например `http://zaborprofil` или `http://localhost:8080`).

| Метод | Путь | Описание |
|-------|------|----------|
| GET | /api/catalog | Каталог (категории + товары, пагинация) |
| GET | /api/catalog/categories | Только категории |
| GET | /api/product/{slug} | Товар по slug |
| POST | /api/calculate | Расчёт по конфигурации |
| POST | /api/estimate | Создание сметы |
| GET | /api/estimate/{id} | Получение сметы |
| POST | /api/order | Создание заказа |
| POST | /api/lead | Отправка лида |
| POST | /api/chat | AI-чат |
| GET | /api/documents/quote/{estimateId} | PDF сметы |

Клиент на фронте: `nextjs/lib/api/client.ts` (типы и вызовы).

## Контент и каталог

- **Редактирование контента сайта (страницы, блоки, медиа):** Payload CMS в Next.js — `http://localhost:3000/admin` (или хост Next.js + `/admin`).
- **Редактирование каталога (категории, товары, варианты):** EasyAdmin в Symfony — `http://zaborprofil/admin`.

Сущности Symfony для каталога: `Category`, `Product`, `ProductVariant`. В EasyAdmin используются CRUD-контроллеры в `symfony/src/Controller/Admin/`. У сущностей должен быть `__toString()` для отображения в связях (AssociationField).

## Запуск (кратко)

- **Symfony:** `cd symfony && composer install && php bin/console doctrine:migrations:migrate && php -S localhost:8080 -t public` (или веб-сервер с document root `symfony/public`).
- **Next.js:** `cd nextjs && npm install && npm run dev`.
- **Payload:** первый пользователь создаётся при первом заходе на `/admin` в Next.js.

## Языки и соглашения

- Код и комментарии — по необходимости на русском или английском; интерфейс админки (EasyAdmin) — русские подписи (Категории, Товары, Варианты товаров).
- Symfony: PHP 8.4, атрибуты (Route, ORM), строгая типизация; контроллеры API в `src/Controller/Api/`, админка в `src/Controller/Admin/`.
- Next.js: TypeScript, App Router; Payload — коллекции в `collections/`, блоки в `blocks/`, конфиг в `payload.config.ts`.

## Частые задачи

- Добавить поле в каталог: сущность в `symfony/src/Catalog/...`, миграция, при необходимости обновить CRUD EasyAdmin и API.
- Добавить блок/коллекцию Payload: новый файл в `blocks/` или `collections/`, подключение в `payload.config.ts` и в коллекции (например `Pages`).
- Изменить маршруты админки Symfony: `config/routes/easyadmin.yaml` (prefix), атрибут `AdminDashboard` в `DashboardController`.
- Ошибка «could not be converted to string» в EasyAdmin: добавить `__toString()` в соответствующую сущность (Category, Product, ProductVariant).
