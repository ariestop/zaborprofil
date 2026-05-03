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
- raster images переупаковываются `MediaOptimizer`, чтобы убрать EXIF/metadata;
- WebP/AVIF variants создаются в `uploads/media/variants/`, если PHP runtime поддерживает нужный encoder.

## Разрешенные типы

- `jpg`, `jpeg` — `image/jpeg`
- `png` — `image/png`
- `webp` — `image/webp`
- `avif` — `image/avif`
- `pdf` — `application/pdf`

SVG сейчас запрещен. Если SVG понадобится, он должен идти через отдельную
политику sanitization с запретом `script`, `foreignObject` и inline event
handlers.

## Обработка изображений

`MediaOptimizer` использует GD runtime без обязательной внешней зависимости:

- JPEG/PNG/WebP/AVIF оригинал переупаковывается, что удаляет EXIF и вспомогательные metadata;
- создаются responsive variants шириной `320`, `768`, `1280`, если исходник шире;
- WebP создаётся при наличии `imagewebp`, AVIF — при наличии `imageavif`;
- если encoder недоступен, загрузка остается успешной, но соответствующий variant не создаётся.
