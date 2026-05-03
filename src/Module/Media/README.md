# Media

Модуль отвечает за медиа-библиотеку, загрузку файлов и генерацию вариантов изображений.

- `MediaApiController` предоставляет admin API для list/upload/delete.
- `UploadValidator` проверяет расширение, MIME, размер, dimensions и опасные двойные расширения.
- `MediaOptimizer` переупаковывает raster images для удаления EXIF/metadata и создаёт WebP/AVIF variants при поддержке PHP runtime.
- SVG запрещён по умолчанию; safe SVG sanitization должен добавляться отдельной политикой.
