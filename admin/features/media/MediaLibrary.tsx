import { useRef, useState, type ChangeEvent, type DragEvent } from 'react'
import {
    DEFAULT_MEDIA_LIST_PARAMS,
    MEDIA_FOLDER_NONE,
    useDeleteMediaAssetMutation,
    useMediaAssetsQuery,
    useMediaFoldersQuery,
    type MediaSort,
    type MediaTypeFilter,
} from '../../entities/media/api'
import { useToast } from '../../app/providers/toast-provider'
import { Button, EmptyState, ErrorState, Input, Skeleton } from '../../shared/ui'
import { cn } from '../../shared/lib/cn'
import { ApiError } from '../../shared/api/client'
import type { MediaAssetItem } from '../../types/api'
import { DeleteMediaDialog } from './DeleteMediaDialog'
import { MediaAssetDetails } from './MediaAssetDetails'
import { MediaAssetGrid, MediaAssetList } from './MediaAssetViews'
import { MediaFilterRow } from './MediaFilterRow'
import { MediaPagination } from './MediaPagination'
import { MediaUploadList } from './MediaUploadList'
import { SELECT_CLASS, SORT_OPTIONS, TYPE_OPTIONS } from './mediaLibraryOptions'
import { describeMediaError, MEDIA_ACCEPT } from './utils'
import { useMediaLibraryFilters } from './useMediaLibraryFilters'
import { useMediaUploads } from './useMediaUploads'

type ViewMode = 'grid' | 'list'

interface MediaLibraryProps {
    mode: 'manage' | 'select'
    onSelect?: (asset: MediaAssetItem) => void
    imagesOnly?: boolean
    perPage?: number
    className?: string
}

export function MediaLibrary({
    mode,
    onSelect,
    imagesOnly = false,
    perPage = DEFAULT_MEDIA_LIST_PARAMS.perPage,
    className,
}: MediaLibraryProps) {
    const { push } = useToast()
    const filters = useMediaLibraryFilters(imagesOnly, perPage)
    const { page, setPage } = filters
    const [view, setView] = useState<ViewMode>('grid')
    const [selectedAsset, setSelectedAsset] = useState<MediaAssetItem | null>(null)
    const [pendingDelete, setPendingDelete] = useState<MediaAssetItem | null>(null)
    const [dragging, setDragging] = useState(false)
    const fileInput = useRef<HTMLInputElement>(null)
    const dragDepth = useRef(0)

    const query = useMediaAssetsQuery(filters.params)
    const foldersQuery = useMediaFoldersQuery()
    const folders = foldersQuery.data ?? []
    const deleteMutation = useDeleteMediaAssetMutation()
    const uploadFolder =
        filters.folderFilter !== '' && filters.folderFilter !== MEDIA_FOLDER_NONE
            ? filters.folderFilter
            : undefined
    const uploads = useMediaUploads(setSelectedAsset, uploadFolder)

    const assets = query.data?.assets ?? []
    const pagination = query.data?.pagination
    const selectedId = selectedAsset?.id ?? null
    const selected = assets.find((asset) => asset.id === selectedId) ?? selectedAsset

    const onFilesPicked = (event: ChangeEvent<HTMLInputElement>): void => {
        if (event.target.files !== null) {
            uploads.addFiles(Array.from(event.target.files))
        }
        event.target.value = ''
    }

    const hasFiles = (event: DragEvent): boolean =>
        Array.from(event.dataTransfer.types).includes('Files')

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
            const inUse = error instanceof ApiError && error.serverCode === 'MEDIA_IN_USE'
            push({
                title: inUse ? 'Файл используется на сайте' : 'Не удалось удалить файл',
                description: describeMediaError(error),
            })
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
            onDragOver={(event) => {
                if (hasFiles(event)) event.preventDefault()
            }}
            onDragLeave={onDragLeave}
            onDrop={onDrop}
            data-testid="media-library"
        >
            {dragging ? (
                <div
                    className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl border-2 border-dashed border-brand-500 bg-brand-50/90 text-sm font-semibold text-brand-800 dark:bg-brand-950/80 dark:text-brand-200"
                    data-testid="drop-overlay"
                >
                    Отпустите файлы, чтобы загрузить
                </div>
            ) : null}

            <div className="flex flex-wrap items-center gap-2">
                <Input
                    type="search"
                    aria-label="Поиск по медиатеке"
                    placeholder="Поиск по имени, alt или title"
                    className="max-w-xs"
                    value={filters.searchInput}
                    onChange={(event) => filters.setSearch(event.target.value)}
                />
                {imagesOnly ? null : (
                    <select
                        aria-label="Тип файлов"
                        className={SELECT_CLASS}
                        value={filters.typeFilter}
                        onChange={(event) =>
                            filters.setTypeFilter(event.target.value as MediaTypeFilter)
                        }
                    >
                        {TYPE_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                )}
                <select
                    aria-label="Сортировка"
                    className={SELECT_CLASS}
                    value={filters.sort}
                    onChange={(event) => filters.setSort(event.target.value as MediaSort)}
                >
                    {SORT_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <div
                    className="flex overflow-hidden rounded-lg border border-line-strong dark:border-slate-700"
                    role="group"
                    aria-label="Вид"
                >
                    <button
                        type="button"
                        aria-pressed={view === 'grid'}
                        className={cn(
                            'px-3 py-2 text-sm',
                            view === 'grid'
                                ? 'bg-brand-600 text-white'
                                : 'bg-white dark:bg-slate-900',
                        )}
                        onClick={() => setView('grid')}
                    >
                        Сетка
                    </button>
                    <button
                        type="button"
                        aria-pressed={view === 'list'}
                        className={cn(
                            'px-3 py-2 text-sm',
                            view === 'list'
                                ? 'bg-brand-600 text-white'
                                : 'bg-white dark:bg-slate-900',
                        )}
                        onClick={() => setView('list')}
                    >
                        Список
                    </button>
                </div>
                <div className="ml-auto">
                    <Button type="button" onClick={() => fileInput.current?.click()}>
                        Загрузить файлы
                    </Button>
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

            <MediaFilterRow filters={filters} folders={folders} imagesOnly={imagesOnly} />

            {uploads.items.length === 0 ? (
                <p className="rounded-xl border border-dashed border-line-strong px-4 py-3 text-center text-sm text-graphite dark:border-slate-700 dark:text-slate-400">
                    Перетащите файлы сюда или нажмите «Загрузить файлы». JPG, PNG, WebP, AVIF
                    {imagesOnly ? '' : ', PDF'} до 10 МБ.
                </p>
            ) : (
                <MediaUploadList
                    items={uploads.items}
                    onCancel={uploads.cancel}
                    onRetry={uploads.retry}
                    onClearFinished={uploads.clearFinished}
                />
            )}

            <div
                className={cn(
                    'grid gap-4',
                    selected !== null ? 'lg:grid-cols-[minmax(0,1fr)_320px]' : '',
                )}
            >
                <div className="min-w-0 space-y-3">
                    {query.isLoading ? (
                        <div
                            className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
                            aria-busy="true"
                        >
                            {Array.from({ length: 8 }, (_, index) => (
                                <Skeleton key={index} className="h-36 w-full" />
                            ))}
                        </div>
                    ) : null}

                    {query.isError ? (
                        <ErrorState
                            title="Не удалось загрузить медиатеку"
                            description={describeMediaError(query.error)}
                        />
                    ) : null}

                    {query.isSuccess &&
                    assets.length === 0 &&
                    pagination !== undefined &&
                    pagination.total > 0 &&
                    page > pagination.totalPages ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPage(pagination.totalPages)}
                        >
                            Перейти на последнюю страницу
                        </Button>
                    ) : null}

                    {query.isSuccess &&
                    assets.length === 0 &&
                    (pagination === undefined || pagination.total === 0) ? (
                        <EmptyState
                            title={filters.filtersActive ? 'Ничего не найдено' : 'Медиатека пуста'}
                            description={
                                filters.filtersActive
                                    ? 'Измените поисковый запрос или фильтры.'
                                    : 'Загрузите первые изображения — они появятся здесь.'
                            }
                        />
                    ) : null}

                    {assets.length > 0 && view === 'grid' ? (
                        <MediaAssetGrid
                            assets={assets}
                            selectedId={selectedId}
                            mode={mode}
                            onActivate={activate}
                            onChoose={(asset) => onSelect?.(asset)}
                        />
                    ) : null}

                    {assets.length > 0 && view === 'list' ? (
                        <MediaAssetList
                            assets={assets}
                            selectedId={selectedId}
                            mode={mode}
                            onActivate={activate}
                            onChoose={(asset) => onSelect?.(asset)}
                        />
                    ) : null}

                    {pagination !== undefined && pagination.total > 0 ? (
                        <MediaPagination
                            pagination={pagination}
                            page={page}
                            pageSize={filters.pageSize}
                            perPage={perPage}
                            onPageChange={setPage}
                            onPageSizeChange={filters.setPageSize}
                        />
                    ) : null}
                </div>

                {selected !== null ? (
                    <MediaAssetDetails
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
                onConfirm={(force) => {
                    void confirmDelete(force)
                }}
            />
        </div>
    )
}
