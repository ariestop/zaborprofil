import { useState } from 'react'
import { useMediaUsagesQuery, useUpdateMediaAssetMutation } from '../../entities/media/api'
import { useToast } from '../../app/providers/toast-provider'
import { Button, Input, Textarea } from '../../shared/ui'
import type { MediaAssetItem, MediaFolderItem } from '../../types/api'
import { useCan } from '../../stores/auth'
import { FocalPointPicker } from './FocalPointPicker'
import { MediaAssetThumb } from './MediaAssetThumb'
import { MediaUsageList } from './MediaUsageList'
import { assetExtension, describeMediaError, formatFileSize, isImageAsset } from './utils'

/** Свойства выбранного файла: превью, где используется, alt/title/описание/папка, фокальная точка. */
export function MediaAssetDetails({
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
    const savedFocal =
        asset.focalX != null && asset.focalY != null ? { x: asset.focalX, y: asset.focalY } : null
    const focalChanged = focal?.x !== savedFocal?.x || focal?.y !== savedFocal?.y
    const dirty =
        alt !== (asset.alt ?? '') ||
        title !== (asset.title ?? '') ||
        description !== (asset.description ?? '') ||
        folder.trim() !== (asset.folder ?? '') ||
        focalChanged

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
        <aside
            className="space-y-3 rounded-xl border border-line p-3 dark:border-slate-700"
            aria-label="Свойства файла"
            data-testid="asset-details"
        >
            {isImageAsset(asset) ? (
                <a href={asset.publicPath} target="_blank" rel="noreferrer" className="block">
                    <img
                        src={asset.publicPath}
                        alt={asset.alt ?? asset.originalName}
                        className="max-h-56 w-full rounded-lg bg-surface-strong object-contain dark:bg-slate-800"
                    />
                </a>
            ) : (
                <MediaAssetThumb asset={asset} className="h-32 w-full rounded-lg" />
            )}

            <div className="space-y-1 text-xs text-graphite dark:text-slate-300">
                <p className="break-all text-sm font-semibold text-ink dark:text-slate-100">
                    {asset.originalName}
                </p>
                <p>
                    {assetExtension(asset)} · {asset.mimeType} · {formatFileSize(asset.size)}
                    {asset.width !== null && asset.height !== null
                        ? ` · ${asset.width}×${asset.height}`
                        : ''}
                </p>
                <p className="break-all">{asset.publicPath}</p>
                <p>Загружен: {new Date(asset.createdAt).toLocaleString('ru-RU')}</p>
                {asset.variants.length > 0 ? <p>Вариантов: {asset.variants.length}</p> : null}
                {asset.fileHash !== null && asset.fileHash !== undefined ? (
                    <p className="break-all" title={asset.fileHash}>
                        SHA-256: {asset.fileHash.slice(0, 16)}…
                    </p>
                ) : null}
            </div>

            <section
                aria-label="Где используется"
                className="space-y-1.5"
                data-testid="asset-usages"
            >
                <h3 className="text-xs font-semibold text-ink dark:text-slate-200">
                    Где используется
                </h3>
                {usagesQuery.isLoading ? (
                    <p className="text-xs text-graphite dark:text-slate-500">Проверяем…</p>
                ) : null}
                {usagesQuery.isError ? (
                    <p className="text-xs text-red-700 dark:text-red-400" role="alert">
                        Не удалось получить список: {describeMediaError(usagesQuery.error)}
                    </p>
                ) : null}
                {usagesQuery.isSuccess && usagesQuery.data.total === 0 ? (
                    <p className="text-xs text-graphite dark:text-slate-500">
                        Нигде не используется.
                    </p>
                ) : null}
                {usagesQuery.isSuccess && usagesQuery.data.total > 0 ? (
                    <>
                        <p className="text-xs text-graphite dark:text-slate-500">
                            Мест: {usagesQuery.data.total}
                        </p>
                        <MediaUsageList
                            usages={usagesQuery.data.usages}
                            className="max-h-40 space-y-1.5 overflow-y-auto"
                        />
                    </>
                ) : null}
            </section>

            <label className="block text-xs font-medium text-ink dark:text-slate-200">
                Alt (описание для SEO и доступности)
                <Input
                    className="mt-1"
                    value={alt}
                    maxLength={255}
                    onChange={(event) => setAlt(event.target.value)}
                    placeholder="Забор из профнастила с кирпичными столбами"
                />
            </label>
            <label className="block text-xs font-medium text-ink dark:text-slate-200">
                Title (всплывающая подсказка)
                <Input
                    className="mt-1"
                    value={title}
                    maxLength={255}
                    onChange={(event) => setTitle(event.target.value)}
                />
            </label>
            <label className="block text-xs font-medium text-ink dark:text-slate-200">
                Описание (для редакторов)
                <Textarea
                    className="mt-1 min-h-16"
                    value={description}
                    maxLength={2000}
                    onChange={(event) => setDescription(event.target.value)}
                />
            </label>
            <label className="block text-xs font-medium text-ink dark:text-slate-200">
                Папка
                <Input
                    className="mt-1"
                    value={folder}
                    maxLength={120}
                    list="media-folder-options"
                    onChange={(event) => setFolder(event.target.value)}
                    placeholder="Например, Заборы"
                />
                <datalist id="media-folder-options">
                    {folders.map((item) => (
                        <option key={item.name} value={item.name} />
                    ))}
                </datalist>
            </label>
            {isImageAsset(asset) ? (
                <FocalPointPicker asset={asset} value={focal} onChange={setFocal} />
            ) : null}
            {saveError !== null ? (
                <p className="text-xs text-red-700 dark:text-red-400" role="alert">
                    {saveError}
                </p>
            ) : null}

            <div className="flex flex-wrap gap-2">
                {mode === 'select' ? (
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => {
                            void select()
                        }}
                        disabled={updateMutation.isPending}
                    >
                        Выбрать
                    </Button>
                ) : null}
                <Button
                    type="button"
                    size="sm"
                    variant={mode === 'select' ? 'outline' : 'default'}
                    onClick={() => {
                        void save()
                    }}
                    disabled={!dirty || updateMutation.isPending}
                >
                    {updateMutation.isPending ? 'Сохранение...' : 'Сохранить'}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => {
                        void copyUrl()
                    }}
                >
                    Копировать ссылку
                </Button>
                {canDelete ? (
                    <Button type="button" size="sm" variant="danger" onClick={onDelete}>
                        Удалить
                    </Button>
                ) : null}
            </div>
        </aside>
    )
}
