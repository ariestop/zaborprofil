# 25. Files и uploads

См. также [UPLOAD_SECURITY.md](legacy/UPLOAD_SECURITY.md).

## Расположение

- Web root: `public_html/`.
- Загруженные файлы: `public_html/uploads/`.
- В Docker — отдельный named volume `uploads_data`.
- На VPS — `shared/public_html/uploads/`, симлинк на `releases/<ts>/public_html/uploads`.

## Public vs private

| Тип | Где хранить | Доступ |
|---|---|---|
| Изображения публикаций / медиа | `public_html/uploads/<resource>/<id>/...` | прямой URL |
| Внутренние файлы (заявки, подтверждения) | `var/share/private/<resource>/...` (целевое — `APP_SHARE_DIR`) | через Symfony controller с авторизацией |
| Backups | `shared/backups/` (вне web root) | только SSH/cron |

## Validation

`UploadValidator` (`src/Shared/Infrastructure/Upload/UploadValidator.php`):

- Проверка размера (`max_file_size` через config).
- MIME через `finfo` (NOT через extension!).
- Whitelist расширений.
- Имя файла: только `[a-z0-9._-]`, transliteration кириллицы, без `..`.
- Возвращает `ValidatedUpload` или бросает `UploadSecurityException`.

```php
final class UploadController
{
    public function __construct(
        private readonly UploadValidator $validator,
        private readonly FileStorageInterface $storage,
        private readonly LoggerInterface $mediaLogger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $upload = $request->files->get('file');
        $validated = $this->validator->validate($upload, 'image'); // throws on violation
        $key = $this->storage->put('media/' . $validated->safeName, $validated->path);
        $this->mediaLogger->info('media.upload.ok', ['key' => $key]);

        return new JsonResponse(['key' => $key]);
    }
}
```

## Image processing (целевое)

- `intervention/image` или `imagine/imagine` для resize / WebP.
- Генерация thumbnails — async через Messenger.
- Хранение оригинала + произведённых вариантов.
- Cache-Control `immutable` для производных.

## Path traversal protection

- Никогда не строить путь как `$root . $userInput`. Использовать:

```php
$absolute = realpath($baseDir . '/' . $relative);
if ($absolute === false || !str_starts_with($absolute, realpath($baseDir))) {
    throw new UploadSecurityException();
}
```

## Safe filename

```php
$sanitized = preg_replace('/[^a-z0-9._-]/i', '-', $original);
$sanitized = strtolower($sanitized);
$final = $ulid->toBase32() . '-' . $sanitized;
```

- ULID-префикс гарантирует уникальность и сортируемость.
- Spaces, `..`, спецсимволы — заменены.
- Никогда не использовать `basename($_FILES['name'])` без санитайза.

## Storage abstraction (целевое)

```php
interface FileStorageInterface
{
    public function put(string $key, string $localPath): string; // returns final URL key
    public function delete(string $key): void;
    public function exists(string $key): bool;
}
```

Реализации:

- `LocalFileStorage` (сейчас) — кладёт в `public_html/uploads/`.
- `S3FileStorage` (целевое) — `league/flysystem-aws-s3-v3` или Symfony `Flysystem` bundle.

Application/Domain работают только с `FileStorageInterface`. Перейти с локального на S3 — без изменения use case.

## Cleanup

Орфаны (загружено, но не сохранено в БД) — целевое: периодический console command `app:media:cleanup-orphans`, удаляет файлы старше 24h без ссылки в БД.

## Backups

См. [36-backup-restore](36-backup-restore.md).

- `shared/public_html/uploads/` бэкапятся отдельно (rsync / object-storage).
- Retention — минимум 30 дней.

## Что нельзя

- Полагаться на `$_FILES['type']` (контролируется клиентом).
- Хранить `.php`, `.phtml`, `.htaccess` в uploads. Nginx должен **запрещать** выполнение PHP в `/uploads/` отдельным `location`.
- Класть user uploads в `public_html/build/` (это для Vite output).
- Удалять файл напрямую без проверки прав (нужен voter).
- Сохранять загруженный файл в БД как `bytea` без причины.

## Nginx safety

В `tools/deploy/templates/nginx-*.conf` `/uploads/` должен иметь:

```nginx
location ~* ^/uploads/.*\.(php|phtml|phar|pht)$ {
    deny all;
    return 403;
}
```

## Anti-patterns

- Trust client MIME / extension.
- Trust filename без санитайза.
- Хранение secret-документов в `public_html/`.
- Отсутствие лимита размера.
- Полагаться только на `enctype="multipart/form-data"` без валидации.

## Чек-лист upload-эндпоинта

- [ ] Лимит размера на nginx + PHP + application.
- [ ] `UploadValidator::validate(...)` вызван.
- [ ] Имя файла санитайзится.
- [ ] Путь сохранения — через `FileStorageInterface`.
- [ ] Авторизация (`media.upload` permission).
- [ ] Логирование канал `media`.
- [ ] Async image processing через Messenger.
- [ ] Functional-тест с фейковыми файлами.

## Связанные документы

- [20-security-and-access-control](20-security-and-access-control.md)
- [11-infrastructure-layer](11-infrastructure-layer.md)
- [36-backup-restore](36-backup-restore.md)
- [UPLOAD_SECURITY.md](legacy/UPLOAD_SECURITY.md)
