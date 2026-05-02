# Безопасность загрузок

`UploadValidator` проверяет файлы до сохранения в `public_html/uploads`.

## Проверки

- Symfony upload status;
- расширение файла;
- MIME type;
- размер файла;
- размеры изображения;
- опасные двойные расширения;
- запрет `php`, `phtml`, `phar`, `html`, `js`, `svg` по умолчанию;
- генерация случайного ULID-имени.

## Разрешенные типы

- `jpg`, `jpeg` — `image/jpeg`
- `png` — `image/png`
- `webp` — `image/webp`
- `avif` — `image/avif`
- `pdf` — `application/pdf`

SVG сейчас запрещен. Если SVG понадобится, он должен идти через отдельную
политику sanitization с запретом `script`, `foreignObject` и inline event
handlers.
