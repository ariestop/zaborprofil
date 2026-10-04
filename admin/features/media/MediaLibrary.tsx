import { useEffect, useRef, useState, type ChangeEvent, type DragEvent, type MouseEvent } from 'react'
import {
  DEFAULT_MEDIA_LIST_PARAMS,
  MEDIA_FOLDER_NONE,
  useDeleteMediaAssetMutation,
  useMediaAssetsQuery,
  useMediaFoldersQuery,
  useMediaUsagesQuery,
  useUpdateMediaAssetMutation,
  type MediaFormatFilter,
  type MediaListParams,
  type MediaSort,
  type MediaTypeFilter,
  type MediaUsageFilter,
} from '../../entities/media/api'
import { useToast } from '../../app/providers/toast-provider'
import { Badge, Button, EmptyState, ErrorState, Input, Skeleton, Textarea } from '../../shared/ui'
import { cn } from '../../shared/lib/cn'
import { ApiError } from '../../shared/api/client'
import type { MediaAssetItem, MediaFolderItem } from '../../types/api'
import { useCan } from '../../stores/auth'
import { DeleteMediaDialog } from './DeleteMediaDialog'
import { MediaUsageList } from './MediaUsageList'
import { assetExtension, describeDuplicate, describeMediaError, formatFileSize, formatUsageCount, isImageAsset, MEDIA_ACCEPT, thumbnailPath, validateUploadFile } from './utils'
import { useMediaUploads, type UploadItem } from './useMediaUploads'

type ViewMode = 'grid' | 'list'

interface MediaLibraryProps {
  mode: 'manage' | 'select'
  onSelect?: (asset: MediaAssetItem) => void
  imagesOnly?: boolean
  perPage?: number
  className?: string
}

const SORT_OPTIONS: Array<{ value: MediaSort; label: string }> = [
  { value: 'newest', label: 'Сначала новые' },
  { value: 'oldest', label: 'Сначала старые' },
  { value: 'name', label: 'По имени' },
  { value: 'size', label: 'Сначала крупные' },
  { value: 'size_asc', label: 'Сначала мелкие' },
]

const FORMAT_OPTIONS: Array<{ value: MediaFormatFilter; label: string }> = [
  { value: '', label: 'Все форматы' },
  { value: 'jpeg', label: 'JPEG' },
  { value: 'png', label: 'PNG' },
  { value: 'webp', label: 'WebP' },
  { value: 'avif', label: 'AVIF' },
  { value: 'pdf', label: 'PDF' },
]

const USAGE_OPTIONS: Array<{ value: MediaUsageFilter; label: string }> = [
  { value: '', label: 'Любое использование' },
  { value: 'used', label: 'Используются' },
  { value: 'unused', label: 'Не используются' },
]

const PER_PAGE_OPTIONS = [24, 48, 96]

const SELECT_CLASS = 'h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-900'

const TYPE_OPTIONS: Array<{ value: MediaTypeFilter; label: string }> = [
  { value: '', label: 'Все файлы' },
  { value: 'image', label: 'Изображения' },
  { value: 'document', label: 'Документы' },
]

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(value), delayMs)
    return () => window.clearTimeout(timer)
  }, [value, delayMs])

  return debounced
}

function AssetThumb({ asset, className }: { asset: MediaAssetItem; className?: string }) {
  const source = thumbnailPath(asset)

  if (source === null) {
    return (
      <div className={cn('flex items-center justify-center bg-slate-100 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800', className)}>
        {asset.mimeType === 'application/pdf' ? 'PDF' : 'Файл'}
      </div>
    )
  }

  return <img src={source} alt={asset.alt ?? asset.originalName} loading="lazy" className={cn('bg-slate-100 object-cover dark:bg-slate-800', className)} />
}

function UsageBadge({ asset }: { asset: MediaAssetItem }) {
  if (asset.usageCount === undefined) {
    return null
  }

  return asset.usageCount > 0 ? (
    <span title={`Используется в ${formatUsageCount(asset.usageCount)}`}><Badge tone="success">Используется · {asset.usageCount}</Badge></span>
  ) : (
    <Badge>Не используется</Badge>
  )
}

function UploadRow({ item, onCancel, onRetry }: { item: UploadItem; onCancel: () => void; onRetry: () => void }) {
  const percent = Math.round(item.progress * 100)

  return (
    <li className="rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700" data-testid="upload-item">
      <div className="flex items-center justify-between gap-3">
        <span className="min-w-0 truncate font-medium">{item.file.name}</span>
        <span className="shrink-0 text-xs text-slate-500">
          {item.status === 'queued' ? 'В очереди' : null}
          {item.status === 'uploading' ? `${percent}%` : null}
          {item.status === 'done' ? (item.asset?.duplicate === true ? 'Уже в медиатеке' : 'Загружен') : null}
          {item.status === 'error' ? 'Ошибка' : null}
        </span>
      </div>
      {item.status === 'uploading' || item.status === 'queued' ? (
        <div
          className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
          role="progressbar"
          aria-label={`Загрузка ${item.file.name}`}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={percent}
        >
          <div className="h-full bg-emerald-600 transition-all" style={{ width: `${percent}%` }} />
        </div>
      ) : null}
      {item.status === 'error' ? <p className="mt-1 text-xs text-red-700 dark:text-red-400" role="alert">{item.error}</p> : null}
      {item.status === 'done' && item.asset !== null && describeDuplicate(item.asset) !== null ? (
        <p className="mt-1 text-xs text-amber-700 dark:text-amber-300" data-testid="duplicate-note">{describeDuplicate(item.asset)}</p>
      ) : null}
      {item.status === 'uploading' || item.status === 'queued' || item.status === 'error' ? (
        <div className="mt-1 flex gap-2">
          {item.status === 'error' && validateUploadFile(item.file) === null ? (
            <Button type="button" size="sm" variant="ghost" onClick={onRetry}>Повторить</Button>
          ) : null}
          <Button type="button" size="sm" variant="ghost" onClick={onCancel}>
            {item.status === 'error' ? 'Скрыть' : 'Отменить'}
          </Button>
        </div>
      ) : null}
    </li>
  )
}

function FocalPointPicker({
  asset,
  value,
  onChange,
}: {
  asset: MediaAssetItem
  value: { x: number; y: number } | null
  onChange: (value: { x: number; y: number } | null) => void
}) {
  const pick = (event: MouseEvent<HTMLButtonElement>): void => {
    const rect = event.currentTarget.getBoundingClientRect()
    if (rect.width === 0 || rect.height === 0) {
      return
    }
    const clamp = (number: number): number => Math.min(100, Math.max(0, Math.round(number)))
    onChange({
      x: clamp(((event.clientX - rect.left) / rect.width) * 100),
      y: clamp(((event.clientY - rect.top) / rect.height) * 100),
    })
  }

  return (
    <section aria-label="Фокальная точка" className="space-y-1.5" data-testid="asset-focal-point">
      <h3 className="text-xs font-semibold text-slate-700 dark:text-slate-200">Фокальная точка</h3>
      <p className="text-xs text-slate-500">Кликните по главному объекту: при обрезке на сайте он останется в кадре.</p>
      <button type="button" aria-label="Выбрать фокальную точку" onClick={pick} className="relative block w-full cursor-crosshair overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
        <img src={asset.publicPath} alt="" className="max-h-48 w-full object-contain" draggable={false} />
        {value !== null ? (
          <span
            data-testid="focal-marker"
            className="pointer-events-none absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-red-600 shadow"
            style={{ left: `${value.x}%`, top: `${value.y}%` }}
          />
        ) : null}
      </button>
      <div className="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
        <span>{value === null ? 'По центру (по умолчанию)' : `X ${value.x}% · Y ${value.y}%`}</span>
        {value !== null ? <Button type="button" variant="ghost" onClick={() => onChange(null)}>Сбросить</Button> : null}
      </div>
    </section>
  )
}

function AssetDetails({
  asset,
  mode,
  onSelect,
  folders,
  onUpdated,
  onDelete,
}: {
  asset: MediaAssetItem
  mode: 'manage' | 'select'
  onSelect?: (asset: MediaAssetItem) => void
  folders: MediaFolderItem[]
  onUpdated: (asset: MediaAssetItem) => void
  onDelete: () => void
}) {
  const { push } = useToast()
  const updateMutation = useUpdateMediaAssetMutation()
  const canDelete = useCan('media.delete')
  const [alt, setAlt] = useState(asset.alt ?? '')
  const [title, setTitle] = useState(asset.title ?? '')
  const [description, setDescription] = useState(asset.description ?? '')
  const [folder, setFolder] = useState(asset.folder ?? '')
  const [focal, setFocal] = useState<{ x: number; y: number } | null>(
    asset.focalX != null && asset.focalY != null ? { x: asset.focalX, y: asset.focalY } : null,
  )
  const [saveError, setSaveError] = useState<string | null>(null)
  const usagesQuery = useMediaUsagesQuery(asset.id)
  const savedFocal = asset.focalX != null && asset.focalY != null ? { x: asset.focalX, y: asset.focalY } : null
  const focalChanged = focal?.x !== savedFocal?.x || focal?.y !== savedFocal?.y
  const dirty =
    alt !== (asset.alt ?? '') || title !== (asset.title ?? '') || description !== (asset.description ?? '') || folder.trim() !== (asset.folder ?? '') || focalChanged

  const save = async (): Promise<MediaAssetItem | null> => {
    setSaveError(null)
    try {
      const updated = await updateMutation.mutateAsync({
        id: asset.id,
        alt,
        title,
        description,
        folder: folder.trim(),
        ...(focalChanged ? { focalX: focal?.x ?? null, focalY: focal?.y ?? null } : {}),
      })
      push({ title: 'Описание файла сохранено' })
      onUpdated(updated)
      return updated
    } catch (error) {
      setSaveError(describeMediaError(error))
      return null
    }
  }

  const select = async (): Promise<void> => {
    let chosen = asset
    if (dirty) {
      const updated = await save()
      if (updated === null) {
        return
      }
      chosen = updated
    }
    onSelect?.(chosen)
  }

  const copyUrl = async (): Promise<void> => {
    try {
      await navigator.clipboard.writeText(asset.publicPath)
      push({ title: 'Ссылка скопирована' })
    } catch {
      push({ title: 'Не удалось скопировать ссылку', description: asset.publicPath })
    }
  }

  return (
    <aside className="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" aria-label="Свойства файла" data-testid="asset-details">
      {isImageAsset(asset) ? (
        <a href={asset.publicPath} target="_blank" rel="noreferrer" className="block">
          <img src={asset.publicPath} alt={asset.alt ?? asset.originalName} className="max-h-56 w-full rounded-lg bg-slate-100 object-contain dark:bg-slate-800" />
        </a>
      ) : (
        <AssetThumb asset={asset} className="h-32 w-full rounded-lg" />
      )}

      <div className="space-y-1 text-xs text-slate-600 dark:text-slate-300">
        <p className="break-all text-sm font-semibold text-slate-900 dark:text-slate-100">{asset.originalName}</p>
        <p>{assetExtension(asset)} · {asset.mimeType} · {formatFileSize(asset.size)}{asset.width !== null && asset.height !== null ? ` · ${asset.width}×${asset.height}` : ''}</p>
        <p className="break-all">{asset.publicPath}</p>
        <p>Загружен: {new Date(asset.createdAt).toLocaleString('ru-RU')}</p>
        {asset.variants.length > 0 ? <p>Вариантов: {asset.variants.length}</p> : null}
        {asset.fileHash !== null && asset.fileHash !== undefined ? <p className="break-all" title={asset.fileHash}>SHA-256: {asset.fileHash.slice(0, 16)}…</p> : null}
      </div>

      <section aria-label="Где используется" className="space-y-1.5" data-testid="asset-usages">
        <h3 className="text-xs font-semibold text-slate-700 dark:text-slate-200">Где используется</h3>
        {usagesQuery.isLoading ? <p className="text-xs text-slate-500">Проверяем…</p> : null}
        {usagesQuery.isError ? <p className="text-xs text-red-700 dark:text-red-400" role="alert">Не удалось получить список: {describeMediaError(usagesQuery.error)}</p> : null}
        {usagesQuery.isSuccess && usagesQuery.data.total === 0 ? <p className="text-xs text-slate-500">Нигде не используется.</p> : null}
        {usagesQuery.isSuccess && usagesQuery.data.total > 0 ? (
          <>
            <p className="text-xs text-slate-500">Мест: {usagesQuery.data.total}</p>
            <MediaUsageList usages={usagesQuery.data.usages} className="max-h-40 space-y-1.5 overflow-y-auto" />
          </>
        ) : null}
      </section>

      <label className="block text-xs font-medium text-slate-700 dark:text-slate-200">
        Alt (описание для SEO и доступности)
        <Input className="mt-1" value={alt} maxLength={255} onChange={(event) => setAlt(event.target.value)} placeholder="Забор из профнастила с кирпичными столбами" />
      </label>
      <label className="block text-xs font-medium text-slate-700 dark:text-slate-200">
        Title (всплывающая подсказка)
        <Input className="mt-1" value={title} maxLength={255} onChange={(event) => setTitle(event.target.value)} />
      </label>
      <label className="block text-xs font-medium text-slate-700 dark:text-slate-200">
        Описание (для редакторов)
        <Textarea className="mt-1 min-h-16" value={description} maxLength={2000} onChange={(event) => setDescription(event.target.value)} />
      </label>
      <label className="block text-xs font-medium text-slate-700 dark:text-slate-200">
        Папка
        <Input className="mt-1" value={folder} maxLength={120} list="media-folder-options" onChange={(event) => setFolder(event.target.value)} placeholder="Например, Заборы" />
        <datalist id="media-folder-options">
          {folders.map((item) => <option key={item.name} value={item.name} />)}
        </datalist>
      </label>
      {isImageAsset(asset) ? <FocalPointPicker asset={asset} value={focal} onChange={setFocal} /> : null}
      {saveError !== null ? <p className="text-xs text-red-700 dark:text-red-400" role="alert">{saveError}</p> : null}

      <div className="flex flex-wrap gap-2">
        {mode === 'select' ? (
          <Button type="button" size="sm" onClick={() => { void select() }} disabled={updateMutation.isPending}>Выбрать</Button>
        ) : null}
        <Button type="button" size="sm" variant={mode === 'select' ? 'outline' : 'default'} onClick={() => { void save() }} disabled={!dirty || updateMutation.isPending}>
          {updateMutation.isPending ? 'Сохранение...' : 'Сохранить'}
        </Button>
        <Button type="button" size="sm" variant="outline" onClick={() => { void copyUrl() }}>Копировать ссылку</Button>
        {canDelete ? <Button type="button" size="sm" variant="danger" onClick={onDelete}>Удалить</Button> : null}
      </div>
    </aside>
  )
}

export function MediaLibrary({ mode, onSelect, imagesOnly = false, perPage = DEFAULT_MEDIA_LIST_PARAMS.perPage, className }: MediaLibraryProps) {
  const { push } = useToast()
  const [searchInput, setSearchInput] = useState('')
  const search = useDebouncedValue(searchInput, 300)
  const [page, setPage] = useState(1)
  const [typeFilter, setTypeFilter] = useState<MediaTypeFilter>(imagesOnly ? 'image' : '')
  const [sort, setSort] = useState<MediaSort>('newest')
  const [formatFilter, setFormatFilter] = useState<MediaFormatFilter>('')
  const [usageFilter, setUsageFilter] = useState<MediaUsageFilter>('')
  const [folderFilter, setFolderFilter] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [pageSize, setPageSize] = useState(perPage)
  const [view, setView] = useState<ViewMode>('grid')
  const [selectedAsset, setSelectedAsset] = useState<MediaAssetItem | null>(null)
  const [pendingDelete, setPendingDelete] = useState<MediaAssetItem | null>(null)
  const [dragging, setDragging] = useState(false)
  const fileInput = useRef<HTMLInputElement>(null)
  const dragDepth = useRef(0)

  const invalidRange = dateFrom !== '' && dateTo !== '' && dateFrom > dateTo
  const params: MediaListParams = {
    page,
    perPage: pageSize,
    q: search.trim(),
    type: typeFilter,
    sort,
    folder: folderFilter,
    format: formatFilter,
    usage: usageFilter,
    from: invalidRange ? '' : dateFrom,
    to: invalidRange ? '' : dateTo,
  }
  const query = useMediaAssetsQuery(params)
  const foldersQuery = useMediaFoldersQuery()
  const folders = foldersQuery.data ?? []
  const deleteMutation = useDeleteMediaAssetMutation()
  const uploadFolder = folderFilter !== '' && folderFilter !== MEDIA_FOLDER_NONE ? folderFilter : undefined
  const uploads = useMediaUploads(setSelectedAsset, uploadFolder)
  const filtersActive = search.trim() !== '' || (!imagesOnly && typeFilter !== '') || formatFilter !== '' || usageFilter !== '' || folderFilter !== '' || dateFrom !== '' || dateTo !== ''

  const assets = query.data?.assets ?? []
  const pagination = query.data?.pagination
  const selectedId = selectedAsset?.id ?? null
  const selected = assets.find((asset) => asset.id === selectedId) ?? selectedAsset
  const finishedUploads = uploads.items.filter((item) => item.status === 'done' || item.status === 'error').length

  const resetPage = <T,>(setter: (value: T) => void) => (value: T) => {
    setter(value)
    setPage(1)
  }

  const onFilesPicked = (event: ChangeEvent<HTMLInputElement>): void => {
    if (event.target.files !== null) {
      uploads.addFiles(Array.from(event.target.files))
    }
    event.target.value = ''
  }

  const hasFiles = (event: DragEvent): boolean => Array.from(event.dataTransfer.types).includes('Files')

  const onDragEnter = (event: DragEvent): void => {
    if (!hasFiles(event)) return
    event.preventDefault()
    dragDepth.current += 1
    setDragging(true)
  }

  const onDragLeave = (event: DragEvent): void => {
    if (!hasFiles(event)) return
    dragDepth.current = Math.max(0, dragDepth.current - 1)
    if (dragDepth.current === 0) {
      setDragging(false)
    }
  }

  const onDrop = (event: DragEvent): void => {
    if (!hasFiles(event)) return
    event.preventDefault()
    dragDepth.current = 0
    setDragging(false)
    uploads.addFiles(Array.from(event.dataTransfer.files))
  }

  const resetFilters = (): void => {
    setSearchInput('')
    setTypeFilter(imagesOnly ? 'image' : '')
    setFormatFilter('')
    setUsageFilter('')
    setFolderFilter('')
    setDateFrom('')
    setDateTo('')
    setPage(1)
  }

  const confirmDelete = async (force: boolean): Promise<void> => {
    if (pendingDelete === null) return
    const target = pendingDelete
    try {
      await deleteMutation.mutateAsync(force ? { id: target.id, force: true } : target.id)
      push({ title: 'Файл удалён', description: target.originalName })
      if (selectedId === target.id) {
        setSelectedAsset(null)
      }
      if (assets.length === 1 && page > 1) {
        setPage(page - 1)
      }
    } catch (error) {
      const inUse = error instanceof ApiError && error.status === 409
      push({ title: inUse ? 'Файл используется на сайте' : 'Не удалось удалить файл', description: describeMediaError(error) })
    } finally {
      setPendingDelete(null)
    }
  }

  const activate = (asset: MediaAssetItem): void => {
    setSelectedAsset(asset)
  }

  return (
    <div
      className={cn('relative space-y-4', className)}
      onDragEnter={onDragEnter}
      onDragOver={(event) => { if (hasFiles(event)) event.preventDefault() }}
      onDragLeave={onDragLeave}
      onDrop={onDrop}
      data-testid="media-library"
    >
      {dragging ? (
        <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl border-2 border-dashed border-emerald-500 bg-emerald-50/90 text-sm font-semibold text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-200" data-testid="drop-overlay">
          Отпустите файлы, чтобы загрузить
        </div>
      ) : null}

      <div className="flex flex-wrap items-center gap-2">
        <Input
          type="search"
          aria-label="Поиск по медиатеке"
          placeholder="Поиск по имени, alt или title"
          className="max-w-xs"
          value={searchInput}
          onChange={(event) => { setSearchInput(event.target.value); setPage(1) }}
        />
        {imagesOnly ? null : (
          <select
            aria-label="Тип файлов"
            className={SELECT_CLASS}
            value={typeFilter}
            onChange={(event) => resetPage(setTypeFilter)(event.target.value as MediaTypeFilter)}
          >
            {TYPE_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
          </select>
        )}
        <select aria-label="Сортировка" className={SELECT_CLASS} value={sort} onChange={(event) => resetPage(setSort)(event.target.value as MediaSort)}>
          {SORT_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </select>
        <div className="flex overflow-hidden rounded-lg border border-slate-300 dark:border-slate-700" role="group" aria-label="Вид">
          <button type="button" aria-pressed={view === 'grid'} className={cn('px-3 py-2 text-sm', view === 'grid' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-900')} onClick={() => setView('grid')}>Сетка</button>
          <button type="button" aria-pressed={view === 'list'} className={cn('px-3 py-2 text-sm', view === 'list' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-900')} onClick={() => setView('list')}>Список</button>
        </div>
        <div className="ml-auto">
          <Button type="button" onClick={() => fileInput.current?.click()}>Загрузить файлы</Button>
          <input
            ref={fileInput}
            type="file"
            multiple
            hidden
            accept={imagesOnly ? '.jpg,.jpeg,.png,.webp,.avif,image/*' : MEDIA_ACCEPT}
            onChange={onFilesPicked}
            data-testid="media-file-input"
          />
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2" aria-label="Фильтры медиатеки" role="group">
        <select aria-label="Формат" className={SELECT_CLASS} value={formatFilter} onChange={(event) => resetPage(setFormatFilter)(event.target.value as MediaFormatFilter)}>
          {FORMAT_OPTIONS.filter((option) => !imagesOnly || option.value !== 'pdf').map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </select>
        <select aria-label="Использование" className={SELECT_CLASS} value={usageFilter} onChange={(event) => resetPage(setUsageFilter)(event.target.value as MediaUsageFilter)}>
          {USAGE_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </select>
        <select aria-label="Папка" className={SELECT_CLASS} value={folderFilter} onChange={(event) => resetPage(setFolderFilter)(event.target.value)}>
          <option value="">Все папки</option>
          <option value={MEDIA_FOLDER_NONE}>Без папки</option>
          {folders.map((folder) => <option key={folder.name} value={folder.name}>{`${folder.name} (${folder.count})`}</option>)}
        </select>
        <label className="flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300">
          Загружены с
          <Input type="date" aria-label="Загружены с" className="h-10 w-40" value={dateFrom} max={dateTo || undefined} onChange={(event) => resetPage(setDateFrom)(event.target.value)} />
        </label>
        <label className="flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300">
          по
          <Input type="date" aria-label="Загружены по" className="h-10 w-40" value={dateTo} min={dateFrom || undefined} onChange={(event) => resetPage(setDateTo)(event.target.value)} />
        </label>
        {filtersActive ? <Button type="button" size="sm" variant="ghost" onClick={resetFilters}>Сбросить фильтры</Button> : null}
      </div>
      {invalidRange ? <p className="text-xs text-red-700 dark:text-red-400" role="alert">Дата «с» не может быть позже даты «по».</p> : null}

      {uploads.items.length === 0 ? (
        <p className="rounded-xl border border-dashed border-slate-300 px-4 py-3 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
          Перетащите файлы сюда или нажмите «Загрузить файлы». JPG, PNG, WebP, AVIF{imagesOnly ? '' : ', PDF'} до 10 МБ.
        </p>
      ) : (
        <section aria-label="Загрузки" className="space-y-2">
          <ul className="space-y-2">
            {uploads.items.map((item) => (
              <UploadRow key={item.id} item={item} onCancel={() => uploads.cancel(item.id)} onRetry={() => uploads.retry(item.id)} />
            ))}
          </ul>
          {finishedUploads > 0 ? <Button type="button" size="sm" variant="ghost" onClick={uploads.clearFinished}>Очистить список загрузок</Button> : null}
        </section>
      )}

      <div className={cn('grid gap-4', selected !== null ? 'lg:grid-cols-[minmax(0,1fr)_320px]' : '')}>
        <div className="min-w-0 space-y-3">
          {query.isLoading ? (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4" aria-busy="true">
              {Array.from({ length: 8 }, (_, index) => <Skeleton key={index} className="h-36 w-full" />)}
            </div>
          ) : null}

          {query.isError ? (
            <ErrorState title="Не удалось загрузить медиатеку" description={describeMediaError(query.error)} />
          ) : null}

          {query.isSuccess && assets.length === 0 && pagination !== undefined && pagination.total > 0 && page > pagination.totalPages ? (
            <Button type="button" variant="outline" onClick={() => setPage(pagination.totalPages)}>Перейти на последнюю страницу</Button>
          ) : null}

          {query.isSuccess && assets.length === 0 && (pagination === undefined || pagination.total === 0) ? (
            <EmptyState
              title={filtersActive ? 'Ничего не найдено' : 'Медиатека пуста'}
              description={filtersActive ? 'Измените поисковый запрос или фильтры.' : 'Загрузите первые изображения — они появятся здесь.'}
            />
          ) : null}

          {assets.length > 0 && view === 'grid' ? (
            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4" data-testid="media-grid">
              {assets.map((asset) => (
                <li key={asset.id}>
                  <button
                    type="button"
                    className={cn(
                      'group block w-full overflow-hidden rounded-xl border text-left transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500',
                      asset.id === selectedId ? 'border-emerald-600 ring-2 ring-emerald-500' : 'border-slate-200 hover:border-slate-400 dark:border-slate-700',
                    )}
                    aria-pressed={asset.id === selectedId}
                    onClick={() => activate(asset)}
                    onDoubleClick={() => { if (mode === 'select') onSelect?.(asset) }}
                  >
                    <AssetThumb asset={asset} className="h-28 w-full" />
                    <span className="block truncate px-2 pt-1.5 text-xs font-medium">{asset.originalName}</span>
                    <span className="flex items-center justify-between gap-1 px-2 pb-1.5 text-[11px] text-slate-500">
                      <span>{formatFileSize(asset.size)}</span>
                      <UsageBadge asset={asset} />
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          ) : null}

          {assets.length > 0 && view === 'list' ? (
            <ul className="divide-y divide-slate-200 rounded-xl border border-slate-200 dark:divide-slate-700 dark:border-slate-700" data-testid="media-list">
              {assets.map((asset) => (
                <li key={asset.id}>
                  <button
                    type="button"
                    className={cn('flex w-full items-center gap-3 px-3 py-2 text-left text-sm', asset.id === selectedId ? 'bg-emerald-50 dark:bg-emerald-950/30' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50')}
                    aria-pressed={asset.id === selectedId}
                    onClick={() => activate(asset)}
                    onDoubleClick={() => { if (mode === 'select') onSelect?.(asset) }}
                  >
                    <AssetThumb asset={asset} className="h-12 w-16 shrink-0 rounded-md" />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate font-medium">{asset.originalName}</span>
                      <span className="block truncate text-xs text-slate-500">{asset.alt ?? 'alt не задан'}</span>
                    </span>
                    <UsageBadge asset={asset} />
                    <Badge>{assetExtension(asset)}</Badge>
                    <span className="hidden w-24 shrink-0 text-right text-xs text-slate-500 sm:block">{asset.width !== null && asset.height !== null ? `${asset.width}×${asset.height}` : '—'}</span>
                    <span className="hidden w-24 shrink-0 text-right text-xs text-slate-500 md:block">{new Date(asset.createdAt).toLocaleDateString('ru-RU')}</span>
                    <span className="w-20 shrink-0 text-right text-xs text-slate-500">{formatFileSize(asset.size)}</span>
                  </button>
                </li>
              ))}
            </ul>
          ) : null}

          {pagination !== undefined && pagination.total > 0 ? (
            <nav className="flex flex-wrap items-center justify-between gap-3" aria-label="Страницы медиатеки">
              <span className="text-xs text-slate-500" data-testid="media-range">
                Показано {(pagination.page - 1) * pagination.perPage + 1}–{Math.min(pagination.page * pagination.perPage, pagination.total)} из {pagination.total}
              </span>
              <div className="flex flex-wrap items-center gap-2">
                <select
                  aria-label="Файлов на странице"
                  className={cn(SELECT_CLASS, 'h-8 px-2 text-xs')}
                  value={pageSize}
                  onChange={(event) => resetPage(setPageSize)(Number(event.target.value))}
                >
                  {Array.from(new Set([...PER_PAGE_OPTIONS, perPage])).sort((left, right) => left - right).map((option) => <option key={option} value={option}>{option} на странице</option>)}
                </select>
                {pagination.totalPages > 1 ? (
                  <>
                    <Button type="button" size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(1)}>В начало</Button>
                    <Button type="button" size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Назад</Button>
                    <span className="text-sm" data-testid="media-page-indicator">Страница {pagination.page} из {pagination.totalPages}</span>
                    <Button type="button" size="sm" variant="outline" disabled={page >= pagination.totalPages} onClick={() => setPage(page + 1)}>Вперёд</Button>
                    <Button type="button" size="sm" variant="outline" disabled={page >= pagination.totalPages} onClick={() => setPage(pagination.totalPages)}>В конец</Button>
                  </>
                ) : null}
              </div>
            </nav>
          ) : null}
        </div>

        {selected !== null ? (
          <AssetDetails
            key={selected.id}
            asset={selected}
            mode={mode}
            onSelect={onSelect}
            folders={folders}
            onUpdated={setSelectedAsset}
            onDelete={() => setPendingDelete(selected)}
          />
        ) : null}
      </div>

      <DeleteMediaDialog
        asset={pendingDelete}
        pending={deleteMutation.isPending}
        onCancel={() => setPendingDelete(null)}
        onConfirm={(force) => { void confirmDelete(force) }}
      />
    </div>
  )
}
