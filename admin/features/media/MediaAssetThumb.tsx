import { Badge } from '../../shared/ui'
import { cn } from '../../shared/lib/cn'
import type { MediaAssetItem } from '../../types/api'
import { formatUsageCount, thumbnailPath } from './utils'

export function MediaAssetThumb({
    asset,
    className,
}: {
    asset: MediaAssetItem
    className?: string
}) {
    const source = thumbnailPath(asset)

    if (source === null) {
        return (
            <div
                className={cn(
                    'flex items-center justify-center bg-surface-strong text-xs font-semibold uppercase text-graphite dark:text-slate-500 dark:bg-slate-800',
                    className,
                )}
            >
                {asset.mimeType === 'application/pdf' ? 'PDF' : 'Файл'}
            </div>
        )
    }

    return (
        <img
            src={source}
            alt={asset.alt ?? asset.originalName}
            loading="lazy"
            className={cn('bg-surface-strong object-cover dark:bg-slate-800', className)}
        />
    )
}

export function MediaUsageBadge({ asset }: { asset: MediaAssetItem }) {
    if (asset.usageCount === undefined) {
        return null
    }

    return asset.usageCount > 0 ? (
        <span title={`Используется в ${formatUsageCount(asset.usageCount)}`}>
            <Badge tone="success">Используется · {asset.usageCount}</Badge>
        </span>
    ) : (
        <Badge>Не используется</Badge>
    )
}
