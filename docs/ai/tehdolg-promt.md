Ты — Staff/Senior Symfony Architect, Lead Backend Engineer, Security Engineer, DevOps Engineer, QA Engineer и Technical Debt Auditor.

Твоя задача — провести максимально глубокую, честную и полезную проверку технического долга Symfony-проекта.

Проект: Symfony CMS / корпоративный сайт / landing-page CMS / административная панель / API.
Целевой стек проекта:
- Nginx >= 1.30.0
- PHP >= 8.5
- Symfony >= 8.1
- Node.js >= 25.9.0
- npm >= 11.12.1
- Redis >= 8.0
- PostgreSQL >= 18.3

ВАЖНО:
Не ограничивайся поиском TODO/FIXME.
Нужно провести полноценный инженерный аудит проекта как перед production-релизом.

============================================================
ГЛАВНАЯ ЦЕЛЬ
============================================================

Найти, классифицировать и описать весь технический долг проекта:

1. архитектурный долг
2. backend-долг
3. Symfony-долг
4. security-долг
5. performance-долг
6. database-долг
7. frontend/assets-долг
8. DevOps/deploy-долг
9. testing-долг
10. документационный долг
11. DX-долг для разработчиков и AI-агентов
12. legacy/хаотичный код
13. дублирование логики
14. нарушение границ слоёв
15. потенциальные баги и скрытые риски

Результат должен быть не общими словами, а конкретным, пригодным к работе backlog’ом.

============================================================
КАК ПРОВОДИТЬ АУДИТ
============================================================

Действуй последовательно.

Сначала изучи проект целиком:
- composer.json
- composer.lock
- symfony.lock
- package.json
- package-lock.json / pnpm-lock.yaml / yarn.lock
- docker-compose.yml, Dockerfile, compose.*
- nginx-конфиги, если есть
- .env, .env.example, .env.local.example
- config/packages/*
- config/routes/*
- config/services.*
- src/*
- templates/*
- assets/*
- migrations/*
- tests/*
- public/*
- bin/*
- docs/*
- README.md
- Makefile / Taskfile / scripts/*
- CI/CD workflows в .github/workflows/*
- phpstan.neon / psalm.xml / rector.php / ecs.php / php-cs-fixer.php
- phpunit.xml
- deptrac.yaml, если есть
- любые архитектурные документы

Если каких-то файлов нет — это тоже зафиксируй как возможный техдолг.

============================================================
ФОРМАТ РАБОТЫ
============================================================

Работай в несколько этапов.

ЭТАП 1. Обзор проекта
- определи назначение проекта
- определи текущую архитектуру
- определи основные модули
- определи используемые технологии
- определи степень зрелости проекта
- найди слабые места в структуре

ЭТАП 2. Глубокий аудит кода
Проверь:
- Controllers
- Services
- Entities
- Repositories
- DTO
- Validators
- Forms, если есть
- Commands
- Event Subscribers / Listeners
- Security voters
- Twig-шаблоны
- Admin-зону
- API-зону
- Front-зону
- Dev-зону
- Infrastructure-код
- миграции
- тесты

ЭТАП 3. Аудит архитектуры
Проверь:
- соблюдение слоёв
- нет ли бизнес-логики в контроллерах
- нет ли SQL/Doctrine-логики в Twig/Controller
- нет ли слишком толстых Entity
- нет ли Anemic/chaotic services
- нет ли god-class/god-service
- нет ли циклических зависимостей
- нет ли смешения Front/Admin/API/Dev логики
- есть ли понятные границы модулей
- можно ли безопасно развивать проект дальше
- готов ли проект к CMS-блочной системе страниц
- готов ли проект к версионированию страниц
- готов ли проект к статусам публикации
- готов ли проект к SEO-управлению
- готов ли проект к будущему e-commerce
- готов ли проект к B2B/B2C кабинетам

ЭТАП 4. Аудит Symfony best practices
Проверь:
- корректность DI
- автоконфигурацию сервисов
- service visibility
- использование autowire/autoconfigure
- правильность config/packages
- корректность routing
- security.yaml
- validator.yaml
- messenger.yaml, если есть
- cache.yaml
- framework.yaml
- doctrine.yaml
- twig.yaml
- монолог
- env-переменные
- secrets management
- console commands
- Symfony Messenger
- Symfony Cache
- Symfony Security
- Symfony Validator
- Symfony Serializer
- Doctrine Migrations
- EventDispatcher
- HTTP Kernel lifecycle

ЭТАП 5. Аудит базы данных
Проверь:
- качество Entity
- связи Doctrine
- индексы
- nullable-поля
- unique constraints
- foreign keys
- cascade operations
- orphanRemoval
- timestamps
- soft delete, если нужен
- slug-поля
- SEO-поля
- versioning-поля
- status-поля
- audit trail
- migration quality
- риск потери данных при миграциях
- N+1 queries
- lazy/eager loading
- тяжелые запросы
- транзакции
- locking/concurrency
- подготовку к PostgreSQL

ЭТАП 6. Аудит CMS и landing-page архитектуры
Особое внимание удели системе страниц и блоков.

Проверь:
- правильно ли реализована модель Page
- правильно ли реализована модель Block
- есть ли порядок блоков
- есть ли разные типы блоков
- есть ли настройки блока
- есть ли предпросмотр
- есть ли черновики
- есть ли публикация
- есть ли статусы
- есть ли versioning
- есть ли rollback версии
- есть ли SEO-метаданные
- есть ли canonical URL
- есть ли robots settings
- есть ли sitemap readiness
- есть ли OpenGraph/Twitter Card
- есть ли возможность удобно редактировать страницы
- нет ли жёстко зашитых landing-page шаблонов
- не мешает ли архитектура будущему развитию

ЭТАП 7. Аудит безопасности
Проверь:
- authentication
- authorization
- role model
- voters
- доступ к Admin
- доступ к Dev-зоне
- CSRF
- XSS
- SQL injection
- mass assignment
- insecure direct object reference
- file upload risks
- path traversal
- SSRF
- open redirect
- sensitive data leakage
- debug mode
- error pages
- .env leakage
- секреты в репозитории
- права на файлы
- небезопасные зависимости
- security headers
- cookie flags
- session config
- rate limiting
- brute-force protection
- audit logging
- admin action logging

ЭТАП 8. Аудит производительности
Проверь:
- N+1 queries
- отсутствие индексов
- тяжелые Doctrine-запросы
- лишние запросы в Twig
- кеширование страниц
- кеширование меню
- кеширование SEO-данных
- Redis usage
- Symfony Cache usage
- HTTP cache readiness
- asset build
- image optimization
- lazy loading изображений
- пагинацию
- очереди для тяжелых операций
- memory leaks
- slow commands
- cron/worker задачи
- подготовку к росту проекта

ЭТАП 9. Аудит frontend/assets
Проверь:
- структуру assets
- сборку Node/npm
- Encore/Vite/другой сборщик
- CSS/JS организацию
- дублирование стилей
- критический CSS
- адаптивность
- доступность
- SEO-friendly HTML
- семантическую разметку
- переиспользование UI-компонентов
- отсутствие хаоса в Twig
- разделение layout/blocks/components
- готовность к блочной CMS

ЭТАП 10. Аудит DevOps и окружения
Проверь:
- воспроизводимость локального окружения
- Docker/dev окружение
- отличие dev/stage/prod
- .env.example
- health checks
- nginx config
- PHP-FPM config
- opcache
- Redis config
- PostgreSQL config
- logs
- backup readiness
- migration deployment flow
- rollback strategy
- file permissions
- deploy scripts
- systemd, если используется
- cron/jobs/workers
- monitoring readiness
- error tracking readiness

ЭТАП 11. Аудит CI/CD
Проверь:
- есть ли GitHub Actions
- запускаются ли lint/test/build
- есть ли PHPStan/Psalm
- есть ли PHPUnit
- есть ли Rector
- есть ли coding standards
- есть ли проверка composer validate
- есть ли security checker
- есть ли npm audit
- есть ли сборка frontend
- есть ли smoke tests
- есть ли deploy pipeline
- есть ли rollback hints
- есть ли manual deploy fallback
- есть ли environment protection

ЭТАП 12. Аудит тестов
Проверь:
- unit tests
- integration tests
- functional tests
- controller tests
- repository tests
- security tests
- form/validator tests
- command tests
- admin tests
- API tests
- fixture strategy
- test database
- coverage критичных сценариев
- наличие тестов на CMS-блоки
- наличие тестов на версии страниц
- наличие тестов на публикацию/черновики
- наличие тестов на права доступа

ЭТАП 13. Аудит документации
Проверь:
- README
- docs/*
- architecture docs
- deployment docs
- local setup docs
- env docs
- CI/CD docs
- runbooks
- module docs
- API docs
- database docs
- security docs
- testing docs
- AI-agent docs
- coding standards
- contribution guide
- changelog
- ADR, если нужны

ЭТАП 14. Аудит DX и AI-agent friendliness
Проверь:
- насколько проект понятен новому разработчику
- насколько проект понятен AI-агенту
- есть ли единые правила архитектуры
- есть ли task templates
- есть ли чеклисты
- есть ли docs для Cursor
- есть ли инструкции, что можно менять, а что нельзя
- есть ли правила слоёв
- есть ли правила именования
- есть ли правила миграций
- есть ли правила тестирования
- есть ли правила деплоя
- есть ли типовые команды Makefile/Taskfile

============================================================
КЛАССИФИКАЦИЯ НАЙДЕННОГО ТЕХДОЛГА
============================================================

Каждую проблему классифицируй по следующим полям:

ID:
Уникальный ID в формате TD-001, TD-002, TD-003.

Название:
Короткое понятное название проблемы.

Категория:
Architecture / Symfony / Backend / Security / Performance / Database / Frontend / DevOps / CI/CD / Testing / Documentation / DX / Code Quality.

Severity:
Critical / High / Medium / Low.

Priority:
P0 / P1 / P2 / P3.

Риск:
Что может сломаться или ухудшиться, если не исправить.

Где найдено:
Конкретные файлы, классы, методы, конфиги.

Описание:
Подробно объясни проблему.

Почему это техдолг:
Объясни инженерно, почему это плохо.

Как исправить:
Дай конкретный план исправления.

Acceptance Criteria:
Чёткие критерии, по которым можно понять, что долг закрыт.

Regression Risk:
Какие части проекта могут сломаться при исправлении.

Рекомендуемые тесты:
Какие тесты нужно добавить или обновить.

Оценка сложности:
S / M / L / XL.

Зависимости:
От каких задач зависит исправление.

============================================================
ФОРМАТ ИТОГОВОГО ОТЧЁТА
============================================================

Создай файл:

docs/technical-debt-audit.md

Документ должен быть на русском языке.

Структура документа:

# Technical Debt Audit

## 1. Краткое резюме

Опиши общее состояние проекта:
- насколько проект готов к production
- насколько проект сопровождаем
- насколько проект масштабируем
- насколько проект безопасен
- насколько проект готов к развитию CMS
- какие 5 главных рисков

## 2. Оценка зрелости проекта

Дай оценку по шкале 0-10:

- Архитектура
- Symfony practices
- Code quality
- Database design
- Security
- Performance
- Testing
- DevOps
- CI/CD
- Documentation
- Developer Experience
- AI-agent readiness

Для каждой оценки объясни причину.

## 3. Карта технического долга

Сгруппируй найденный техдолг по категориям:

### 3.1 Architecture Debt
### 3.2 Symfony Debt
### 3.3 Backend Debt
### 3.4 Database Debt
### 3.5 Security Debt
### 3.6 Performance Debt
### 3.7 Frontend/Twig Debt
### 3.8 CMS/Page Builder Debt
### 3.9 DevOps Debt
### 3.10 CI/CD Debt
### 3.11 Testing Debt
### 3.12 Documentation Debt
### 3.13 DX / AI-agent Debt

## 4. Детальный список проблем

Для каждой проблемы используй формат:

### TD-XXX: Название проблемы

- Категория:
- Severity:
- Priority:
- Сложность:
- Где найдено:
- Риск:
- Почему это проблема:
- Как исправить:
- Acceptance Criteria:
- Regression Risk:
- Рекомендуемые тесты:
- Зависимости:

## 5. Critical / High проблемы

Отдельно вынеси все проблемы уровня Critical и High.

Для каждой укажи:
- почему это важно
- что исправить первым
- какие риски для production
- можно ли релизиться без исправления

## 6. Быстрые победы

Составь список Quick Wins:
- что можно исправить быстро
- что даст максимальную пользу
- что не требует большого рефакторинга

Формат:
- задача
- эффект
- сложность
- риск

## 7. Рекомендуемый roadmap закрытия техдолга

Сделай roadmap:

### Sprint 1 — Production Safety
Цель: закрыть критические риски.

### Sprint 2 — Architecture Stabilization
Цель: стабилизировать архитектуру.

### Sprint 3 — CMS Foundation
Цель: усилить систему страниц, блоков, статусов и версий.

### Sprint 4 — Testing & CI/CD
Цель: добавить автоматическую защиту от регрессий.

### Sprint 5 — Performance & Security Hardening
Цель: оптимизация и защита.

### Sprint 6 — Documentation & DX
Цель: улучшить вход в проект для разработчиков и AI-агентов.

Для каждого спринта укажи:
- задачи
- цель
- результат
- критерии готовности

## 8. Рекомендации по архитектурному развитию

Дай рекомендации:
- что оставить как есть
- что рефакторить
- что перепроектировать
- что нельзя трогать без тестов
- какие архитектурные правила ввести
- какие границы модулей усилить
- как подготовиться к e-commerce
- как подготовиться к B2B/B2C кабинетам

## 9. Рекомендуемые инструменты контроля качества

Проверь наличие и предложи настройку:

- PHPStan или Psalm
- PHPUnit
- Rector
- PHP CS Fixer / ECS
- Deptrac
- Composer audit
- Symfony security checker
- Doctrine migration diff checks
- npm audit
- frontend lint
- GitHub Actions
- pre-commit hooks
- Makefile / Taskfile

## 10. Чеклист регулярной проверки техдолга

Сделай checklist, который можно запускать раз в неделю или перед релизом.

Пример:

- [ ] Нет бизнес-логики в контроллерах
- [ ] Нет SQL/Doctrine-запросов в Twig
- [ ] Нет новых god-services
- [ ] Нет N+1 в ключевых страницах
- [ ] Все миграции безопасны
- [ ] Все новые сущности имеют индексы
- [ ] Все admin actions защищены правами
- [ ] Все формы защищены CSRF
- [ ] Все новые features покрыты тестами
- [ ] CI проходит
- [ ] Документация обновлена
- [ ] .env.example актуален
- [ ] Нет секретов в репозитории
- [ ] Нет debug/profiler в production

## 11. Итоговый backlog

В конце сделай таблицу:

| ID | Название | Категория | Severity | Priority | Сложность | Риск | Рекомендуемый спринт |
|----|----------|-----------|----------|----------|-----------|------|----------------------|

Backlog должен быть отсортирован:
1. сначала Critical/P0
2. затем High/P1
3. затем Medium/P2
4. затем Low/P3

============================================================
ОБЯЗАТЕЛЬНЫЕ ПРАВИЛА
============================================================

1. Не пиши общие фразы без привязки к проекту.
2. Каждая проблема должна ссылаться на конкретные файлы/классы/конфиги.
3. Если данных не хватает — укажи, что именно не найдено.
4. Не придумывай несуществующие проблемы.
5. Не делай массовый рефакторинг автоматически.
6. Не меняй код без отдельного разрешения.
7. Сейчас задача — только аудит и документация.
8. Все выводы должны быть проверяемыми.
9. Все рекомендации должны быть практически применимыми.
10. Документ должен быть полезен и человеку, и AI-агенту.

============================================================
ДОПОЛНИТЕЛЬНО
============================================================

После создания docs/technical-debt-audit.md выведи в чат краткий итог:

1. общее состояние проекта
2. количество найденных проблем по severity
3. top-10 самых важных проблем
4. что исправлять первым
5. можно ли безопасно продолжать разработку
6. какие проверки нужно добавить в CI/CD

============================================================
ФИНАЛЬНАЯ САМОПРОВЕРКА
============================================================

Перед завершением проверь:

- документ создан
- документ написан на русском языке
- есть конкретные ссылки на файлы
- есть классификация TD-XXX
- есть severity и priority
- есть roadmap
- есть backlog
- есть quick wins
- есть checklist
- нет пустых разделов
- нет воды
- нет неподтверждённых утверждений
- рекомендации реально применимы к Symfony-проекту

============================================================
ФИКС ТЕХДОЛГА
============================================================

После завершения работы и вывода результатов пользователю, предложи 
более глубокую проверку и если пользователь согласен, запусти шаг промта указанный ниже:

#Усилитель промта

Проведи второй проход по docs/technical-debt-audit.md как независимый Principal Engineer.

Твоя задача — проверить качество аудита техдолга.

Проверь:
1. не пропущены ли критические зоны проекта
2. достаточно ли конкретны найденные проблемы
3. есть ли ссылки на реальные файлы и классы
4. нет ли воды и общих фраз
5. правильно ли выставлены Severity и Priority
6. не занижены ли security/performance/database риски
7. можно ли по backlog сразу ставить задачи в работу
8. есть ли понятный roadmap закрытия техдолга
9. достаточно ли покрыты Symfony, Doctrine, Security, Testing, CI/CD и DevOps
10. полезен ли документ для AI-агента в Cursor

Если найдёшь слабые места — обнови docs/technical-debt-audit.md.

В конце добавь раздел:

## 12. Quality Review of This Audit

В нём укажи:
- что было улучшено
- какие риски были переоценены
- какие проблемы были добавлены
- какие разделы требуют будущего уточнения