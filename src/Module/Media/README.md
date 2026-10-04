# Media

Модуль отвечает за медиа-библиотеку, загрузку файлов и генерацию вариантов изображений.

- `MediaApiController` предоставляет admin API: список с пагинацией, поиском, фильтрами (тип, формат, папка, использование, даты) и сортировкой (`MediaAssetCriteria`, `MediaListRequest`), upload с дедупликацией по `sha256`, частичный `PATCH` для `alt`/`title`/`description`/`folder`, список папок, «где используется» и delete с защитой (`409 MEDIA_IN_USE`, `?force=1`). Ошибки отдаются через `AdminApiErrorResponder`.
- `Application/Usage`: порт `MediaUsageProviderInterface` (тег `app.media.usage_provider`), `MediaUsageFinder`/`MediaUsageIndex`, `MediaPathExtractor`. Провайдеры лежат в модулях-источниках (`Content`, `Catalog`, `Menu`, `Settings`, каталог `Infrastructure/Media`); новый источник ссылок на медиа добавляется ещё одним провайдером.
- UI медиатеки и `MediaPicker`: `admin/features/media`, описание — `docs/admin/media-library.md`.
- `UploadValidator` проверяет расширение, MIME, размер, dimensions и опасные двойные расширения.
- `MediaOptimizer` переупаковывает raster images для удаления EXIF/metadata и создаёт WebP/AVIF variants при поддержке PHP runtime.
- SVG запрещён по умолчанию; safe SVG sanitization должен добавляться отдельной политикой.
- `Application/Responsive` и Twig-функция `responsive_image` (`UI/Twig/ResponsiveImageExtension`) выводят публичные изображения как `<picture>` с AVIF/WebP `srcset`, `width`/`height`, `loading`/`fetchpriority` и `object-position` по фокальной точке ассета (`focalX`/`focalY`); URL из блоков сопоставляется с ассетом по `publicPath`. Подробности — `docs/admin/media-library.md`.
