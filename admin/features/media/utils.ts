import { ApiError } from '../../shared/api/client'
import type { MediaAssetItem, MediaUsageItem } from '../../types/api'

export const MEDIA_ACCEPT = '.jpg,.jpeg,.png,.webp,.avif,.pdf,image/jpeg,image/png,image/webp,image/avif,application/pdf'
export const MEDIA_MAX_UPLOAD_BYTES = 10 * 1024 * 1024

const ALLOWED_MIME_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'application/pdf'])
const ALLOWED_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'webp', 'avif', 'pdf'])

const SERVER_MESSAGES: Array<[string, string]> = [
  ['Uploaded file extension is not allowed', 'Недопустимый тип файла. Разрешены JPG, PNG, WebP, AVIF и PDF.'],
  ['Uploaded file MIME type is not allowed', 'Содержимое файла не соответствует разрешённым форматам.'],
  ['Uploaded file size is not allowed', 'Файл пустой или больше 10 МБ.'],
  ['Uploaded file exceeds the allowed size', 'Файл больше допустимого размера.'],
  ['Uploaded file is not valid', 'Файл не удалось загрузить на сервер.'],
  ['dangerous', 'Имя файла содержит опасное расширение.'],
  ['dimensions are not allowed', 'Размеры изображения превышают 8000×8000 пикселей.'],
  ['dimensions cannot be read', 'Не удалось прочитать изображение: файл повреждён.'],
  ['Field "file" must contain an uploaded file', 'Файл не получен сервером. Проверьте лимит размера загрузки.'],
  ['Access denied', 'Недостаточно прав для этого действия.'],
  ['Media asset not found', 'Файл не найден в медиатеке.'],
  ['cannot be longer than', 'Значение слишком длинное.'],
  ['folder contains forbidden characters', 'Название папки не должно содержать «/», «\\» и служебные символы.'],
  ['Upload date range is invalid', 'Дата «с» не может быть позже даты «по».'],
  ['Media asset is used on the site', 'Файл используется на сайте. Подтвердите удаление.'],
  ['Internal server error', 'Внутренняя ошибка сервера. Повторите попытку позже.'],
]

export function formatFileSize(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} Б`
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1).replace('.', ',')} КБ`
  }

  return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} МБ`
}

export function isImageAsset(asset: Pick<MediaAssetItem, 'mimeType'>): boolean {
  return asset.mimeType.startsWith('image/')
}

export function thumbnailPath(asset: MediaAssetItem): string | null {
  if (!isImageAsset(asset)) {
    return null
  }

  const preview = asset.variants
    .filter((variant) => variant.type === 'webp' && variant.width !== null)
    .sort((left, right) => (left.width ?? 0) - (right.width ?? 0))
    .find((variant) => (variant.width ?? 0) >= 320)

  return preview?.publicPath ?? asset.publicPath
}

export function validateUploadFile(file: Pick<File, 'name' | 'size' | 'type'>): string | null {
  const extension = file.name.includes('.') ? file.name.split('.').pop()?.toLowerCase() ?? '' : ''

  if (!ALLOWED_EXTENSIONS.has(extension) || (file.type !== '' && !ALLOWED_MIME_TYPES.has(file.type))) {
    return 'Недопустимый тип файла. Разрешены JPG, PNG, WebP, AVIF и PDF.'
  }

  if (file.size <= 0) {
    return 'Файл пустой.'
  }

  if (file.size > MEDIA_MAX_UPLOAD_BYTES) {
    return `Файл больше ${formatFileSize(MEDIA_MAX_UPLOAD_BYTES)}.`
  }

  return null
}

export function describeMediaError(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.status === 413) {
      return 'Файл больше лимита сервера. Уменьшите размер файла.'
    }

    if (error.status === 401) {
      return 'Сессия истекла. Обновите страницу и войдите снова.'
    }

    if (error.status === 403 && error.message.includes('CSRF')) {
      return 'Сессия устарела. Обновите страницу и повторите попытку.'
    }

    const known = SERVER_MESSAGES.find(([fragment]) => error.message.includes(fragment))
    if (known !== undefined) {
      return known[1]
    }

    return error.message
  }

  if (error instanceof Error && error.message === 'Network error') {
    return 'Нет соединения с сервером.'
  }

  return 'Не удалось выполнить операцию.'
}

export function isImagePath(path: string): boolean {
  return /\.(jpe?g|png|webp|avif|gif|svg)(\?.*)?$/i.test(path) || path.startsWith('/uploads/')
}

const USAGE_TYPE_LABELS: Record<MediaUsageItem['type'], string> = {
  page_seo: 'Страница (SEO)',
  page_block: 'Страница (блок)',
  product: 'Товар',
  category: 'Категория',
  menu_item: 'Меню',
  setting: 'Настройки',
}

export function usageTypeLabel(type: MediaUsageItem['type']): string {
  return USAGE_TYPE_LABELS[type]
}

/** «1 месте», «2 местах», «21 месте» — для оборота «используется в …». */
export function formatUsageCount(count: number): string {
  return `${count} ${count % 10 === 1 && count % 100 !== 11 ? 'месте' : 'местах'}`
}

export function assetExtension(asset: Pick<MediaAssetItem, 'filename' | 'mimeType'>): string {
  const extension = asset.filename.includes('.') ? (asset.filename.split('.').pop() ?? '') : ''

  return (extension !== '' ? extension : (asset.mimeType.split('/')[1] ?? '')).toUpperCase()
}

export function describeDuplicate(asset: Pick<MediaAssetItem, 'duplicate' | 'originalName'>): string | null {
  return asset.duplicate === true ? `Такой файл уже есть в медиатеке («${asset.originalName}») — использован существующий.` : null
}
