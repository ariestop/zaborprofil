import { useEffect, useRef, useState, type ChangeEvent, type DragEvent } from 'react'
import {
  DEFAULT_MEDIA_LIST_PARAMS,
  useDeleteMediaAssetMutation,
  useMediaAssetsQuery,
  useUpdateMediaAssetMutation,
  type MediaListParams,
  type MediaSort,
  type MediaTypeFilter,
} from '../../entities/media/api'
import { useToast } from '../../app/providers/toast-provider'
import { Badge, Button, ConfirmDialog, EmptyState, ErrorState, Input, Skeleton } from '../../shared/ui'
import { cn } from '../../shared/lib/cn'
import type { MediaAssetItem } from '../../types/api'
import { describeMediaError, formatFileSize, isImageAsset, MEDIA_ACCEPT, thumbnailPath, validateUploadFile } from './utils'
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
  { value: 'size', label: 'По размеру' },
]

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

function UploadRow({ item, onCancel, onRetry }: { item: UploadItem; onCancel: () => void; onRetry: () => void }) {
  const percent = Math.round(item.progress * 100)

  return (
    <li className="rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700" data-testid="upload-item">
      <div className="flex items-center justify-between gap-3">
        <span className="min-w-0 truncate font-medium">{item.file.name}</span>
        <span className="shrink-0 text-xs text-slate-500">
          {item.status === 'queued' ? 'В очереди' : null}
          {item.status === 'uploading' ? `${percent}%` : null}
          {item.status === 'done' ? 'Загружен' : null}
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

function AssetDetails({
  asset,
  mode,
  onSelect,
  onUpdated,
  onDelete,
}: {
  asset: MediaAssetItem
  mode: 'manage' | 'select'
  onSelect?: (asset: MediaAssetItem) => void
  onUpdated: (asset: MediaAssetItem) => void
  onDelete: () => void
}) {
  const { push } = useToast()
  const updateMutation = useUpdateMediaAssetMutation()
  const [alt, setAlt] = useState(asset.alt ?? '')
  const [title, setTitle] = useState(asset.title ?? '')
  const [saveError, setSaveError] = useState<string | null>(null)
  const dirty = alt !== (asset.alt ?? '') || title !== (asset.title ?? '')

  const save = async (): Promise<MediaAssetItem | null> => {
    setSaveError(null)
    try {
      const updated = await updateMutation.mutateAsync({ id: asset.id, alt, title })
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
        <p>{asset.mimeType} · {formatFileSize(asset.size)}{asset.width !== null && asset.height !== null ? ` · ${asset.width}×${asset.height}` : ''}</p>
        <p className="break-all">{asset.publicPath}</p>
        <p>Загружен: {new Date(asset.createdAt).toLocaleString('ru-RU')}</p>
      </div>

      <label className="block text-xs font-medium text-slate-700 dark:text-slate-200">
        Alt (описание для SEO и доступности)
        <Input className="mt-1" value={alt} maxLength={255} onChange={(event) => setAlt(event.target.value)} placeholder="Забор из профнастила с кирпичными столбами" />
      </label>
      <label className="block text-xs font-medium text-slate-700 dark:text-slate-200">
        Title (всплывающая подсказка)
        <Input className="mt-1" value={title} maxLength={255} onChange={(event) => setTitle(event.target.value)} />
      </label>
      {saveError !== null ? <p className="text-xs text-red-700 dark:text-red-400" role="alert">{saveError}</p> : null}

      <div className="flex flex-wrap gap-2">
        {mode === 'select' ? (
          <Button type="button" size="sm" onClick={() => { void select() }} disabled={updateMutation.isPending}>Выбрать</Button>
        ) : null}
        <Button type="button" size="sm" variant={mode === 'select' ? 'outline' : 'default'} onClick={() => { void save() }} disabled={!dirty || updateMutation.isPending}>
          {updateMutation.isPending ? 'Сохранение...' : 'Сохранить'}
        </Button>
        <Button type="button" size="sm" variant="outline" onClick={() => { void copyUrl() }}>Копировать ссылку</Button>
        <Button type="button" size="sm" variant="danger" onClick={onDelete}>Удалить</Button>
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
  const [view, setView] = useState<ViewMode>('grid')
  const [selectedAsset, setSelectedAsset] = useState<MediaAssetItem | null>(null)
  const [pendingDelete, setPendingDelete] = useState<MediaAssetItem | null>(null)
  const [dragging, setDragging] = useState(false)
  const fileInput = useRef<HTMLInputElement>(null)
  const dragDepth = useRef(0)

  const params: MediaListParams = { page, perPage, q: search.trim(), type: typeFilter, sort }
  const query = useMediaAssetsQuery(params)
  const deleteMutation = useDeleteMediaAssetMutation()
  const uploads = useMediaUploads(setSelectedAsset)

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

  const confirmDelete = async (): Promise<void> => {
    if (pendingDelete === null) return
    const target = pendingDelete
    try {
      await deleteMutation.mutateAsync(target.id)
      push({ title: 'Файл удалён', description: target.originalName })
      if (selectedId === target.id) {
        setSelectedAsset(null)
      }
      if (assets.length === 1 && page > 1) {
        setPage(page - 1)
      }
    } catch (error) {
      push({ title: 'Не удалось удалить файл', description: describeMediaError(error) })
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
            className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-900"
            value={typeFilter}
            onChange={(event) => resetPage(setTypeFilter)(event.target.value as MediaTypeFilter)}
          >
            {TYPE_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
          </select>
        )}
        <select
          aria-label="Сортировка"
          className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-900"
          value={sort}
          onChange={(event) => resetPage(setSort)(event.target.value as MediaSort)}
        >
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
              title={search.trim() !== '' || typeFilter !== '' ? 'Ничего не найдено' : 'Медиатека пуста'}
              description={search.trim() !== '' || typeFilter !== '' ? 'Измените поисковый запрос или фильтры.' : 'Загрузите первые изображения — они появятся здесь.'}
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
                    <span className="block px-2 pb-1.5 text-[11px] text-slate-500">{formatFileSize(asset.size)}</span>
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
                    <Badge>{isImageAsset(asset) ? 'Изображение' : 'Документ'}</Badge>
                    <span className="w-20 shrink-0 text-right text-xs text-slate-500">{formatFileSize(asset.size)}</span>
                  </button>
                </li>
              ))}
            </ul>
          ) : null}

          {pagination !== undefined && pagination.totalPages > 1 ? (
            <nav className="flex items-center justify-between gap-3" aria-label="Страницы медиатеки">
              <span className="text-xs text-slate-500">Файлов: {pagination.total}</span>
              <div className="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Назад</Button>
                <span className="text-sm" data-testid="media-page-indicator">Страница {pagination.page} из {pagination.totalPages}</span>
                <Button type="button" size="sm" variant="outline" disabled={page >= pagination.totalPages} onClick={() => setPage(page + 1)}>Вперёд</Button>
              </div>
            </nav>
          ) : pagination !== undefined && pagination.total > 0 ? (
            <p className="text-xs text-slate-500">Файлов: {pagination.total}</p>
          ) : null}
        </div>

        {selected !== null ? (
          <AssetDetails
            key={selected.id}
            asset={selected}
            mode={mode}
            onSelect={onSelect}
            onUpdated={setSelectedAsset}
            onDelete={() => setPendingDelete(selected)}
          />
        ) : null}
      </div>

      <ConfirmDialog
        open={pendingDelete !== null}
        title="Удалить файл?"
        description={pendingDelete === null ? undefined : `«${pendingDelete.originalName}» будет удалён вместе с вариантами. Страницы, где файл используется, потеряют изображение.`}
        confirmLabel="Удалить"
        onCancel={() => setPendingDelete(null)}
        onConfirm={() => { void confirmDelete() }}
      />
    </div>
  )
}
