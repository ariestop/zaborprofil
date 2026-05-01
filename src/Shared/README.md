# Shared

Общий слой для кода, который не принадлежит конкретному бизнес-модулю.

- `Domain` — общие value objects, события, исключения и контракты.
- `Application` — общие DTO, команды, запросы и сервисы приложения.
- `Infrastructure` — адаптеры Doctrine, Redis, Mailer, Cache и Filesystem.
- `UI` — общие HTTP/Web/API entrypoints.

Код из `Shared` не должен знать о деталях конкретных модулей.
