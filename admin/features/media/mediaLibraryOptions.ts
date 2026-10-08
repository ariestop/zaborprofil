import type {
    MediaFormatFilter,
    MediaSort,
    MediaTypeFilter,
    MediaUsageFilter,
} from '../../entities/media/api'

export const SORT_OPTIONS: Array<{ value: MediaSort; label: string }> = [
    { value: 'newest', label: 'Сначала новые' },
    { value: 'oldest', label: 'Сначала старые' },
    { value: 'name', label: 'По имени' },
    { value: 'size', label: 'Сначала крупные' },
    { value: 'size_asc', label: 'Сначала мелкие' },
]

export const FORMAT_OPTIONS: Array<{ value: MediaFormatFilter; label: string }> = [
    { value: '', label: 'Все форматы' },
    { value: 'jpeg', label: 'JPEG' },
    { value: 'png', label: 'PNG' },
    { value: 'webp', label: 'WebP' },
    { value: 'avif', label: 'AVIF' },
    { value: 'pdf', label: 'PDF' },
]

export const USAGE_OPTIONS: Array<{ value: MediaUsageFilter; label: string }> = [
    { value: '', label: 'Любое использование' },
    { value: 'used', label: 'Используются' },
    { value: 'unused', label: 'Не используются' },
]

export const PER_PAGE_OPTIONS = [24, 48, 96]

export const SELECT_CLASS =
    'h-12 rounded-lg border border-line-strong bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-900'

export const TYPE_OPTIONS: Array<{ value: MediaTypeFilter; label: string }> = [
    { value: '', label: 'Все файлы' },
    { value: 'image', label: 'Изображения' },
    { value: 'document', label: 'Документы' },
]
