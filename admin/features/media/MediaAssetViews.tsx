import { Badge } from '../../shared/ui'
import { cn } from '../../shared/lib/cn'
import type { MediaAssetItem } from '../../types/api'
import { MediaAssetThumb, MediaUsageBadge } from './MediaAssetThumb'
import { assetExtension, formatFileSize } from './utils'

interface MediaAssetViewProps {
    assets: MediaAssetItem[]
    selectedId: string | null
    mode: 'manage' | 'select'
    /** Клик — показать свойства файла. */
    onActivate: (asset: MediaAssetItem) => void
    /** Двойной клик в режиме выбора — выбрать файл. */
    onChoose: (asset: MediaAssetItem) => void
}

export function MediaAssetGrid({
    assets,
    selectedId,
    mode,
    onActivate,
    onChoose,
}: MediaAssetViewProps) {
    return (
        <ul
            className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
            data-testid="media-grid"
        >
            {assets.map((asset) => (
                <li key={asset.id}>
                    <button
                        type="button"
                        className={cn(
                            'group block w-full overflow-hidden rounded-xl border text-left transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500',
                            asset.id === selectedId
                                ? 'border-brand-600 ring-2 ring-brand-500'
                                : 'border-line hover:border-line-strong dark:hover:border-slate-400 dark:border-slate-700',
                        )}
                        aria-pressed={asset.id === selectedId}
                        onClick={() => onActivate(asset)}
                        onDoubleClick={() => {
                            if (mode === 'select') onChoose(asset)
                        }}
                    >
                        <MediaAssetThumb asset={asset} className="h-28 w-full" />
                        <span className="block truncate px-2 pt-1.5 text-xs font-medium">
                            {asset.originalName}
                        </span>
                        <span className="flex items-center justify-between gap-1 px-2 pb-1.5 text-[11px] text-graphite dark:text-slate-500">
                            <span>{formatFileSize(asset.size)}</span>
                            <MediaUsageBadge asset={asset} />
                        </span>
                    </button>
                </li>
            ))}
        </ul>
    )
}

export function MediaAssetList({
    assets,
    selectedId,
    mode,
    onActivate,
    onChoose,
}: MediaAssetViewProps) {
    return (
        <ul
            className="divide-y divide-line rounded-xl border border-line dark:divide-slate-700 dark:border-slate-700"
            data-testid="media-list"
        >
            {assets.map((asset) => (
                <li key={asset.id}>
                    <button
                        type="button"
                        className={cn(
                            'flex w-full items-center gap-3 px-3 py-2 text-left text-sm',
                            asset.id === selectedId
                                ? 'bg-brand-50 dark:bg-brand-950/30'
                                : 'hover:bg-surface dark:hover:bg-slate-800/50',
                        )}
                        aria-pressed={asset.id === selectedId}
                        onClick={() => onActivate(asset)}
                        onDoubleClick={() => {
                            if (mode === 'select') onChoose(asset)
                        }}
                    >
                        <MediaAssetThumb asset={asset} className="h-12 w-16 shrink-0 rounded-md" />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate font-medium">{asset.originalName}</span>
                            <span className="block truncate text-xs text-graphite dark:text-slate-500">
                                {asset.alt ?? 'alt не задан'}
                            </span>
                        </span>
                        <MediaUsageBadge asset={asset} />
                        <Badge>{assetExtension(asset)}</Badge>
                        <span className="hidden w-24 shrink-0 text-right text-xs text-graphite dark:text-slate-500 sm:block">
                            {asset.width !== null && asset.height !== null
                                ? `${asset.width}×${asset.height}`
                                : '—'}
                        </span>
                        <span className="hidden w-24 shrink-0 text-right text-xs text-graphite dark:text-slate-500 md:block">
                            {new Date(asset.createdAt).toLocaleDateString('ru-RU')}
                        </span>
                        <span className="w-20 shrink-0 text-right text-xs text-graphite dark:text-slate-500">
                            {formatFileSize(asset.size)}
                        </span>
                    </button>
                </li>
            ))}
        </ul>
    )
}
