# Media

Модуль отвечает за медиа-библиотеку, загрузку файлов и генерацию вариантов изображений.

- `MediaApiController` предоставляет admin API: список с пагинацией, поиском, фильтром по типу и сортировкой (`MediaAssetCriteria`, `MediaListRequest`), upload, `PATCH` для `alt`/`title` и delete. Ошибки отдаются через `AdminApiErrorResponder`.
- UI медиатеки и `MediaPicker`: `admin/features/media`, описание — `docs/admin/media-library.md`.
- `UploadValidator` проверяет расширение, MIME, размер, dimensions и опасные двойные расширения.
- `MediaOptimizer` переупаковывает raster images для удаления EXIF/metadata и создаёт WebP/AVIF variants при поддержке PHP runtime.
- SVG запрещён по умолчанию; safe SVG sanitization должен добавляться отдельной политикой.
