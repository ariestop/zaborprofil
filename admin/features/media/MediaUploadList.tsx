import { Button } from '../../shared/ui'
import { describeDuplicate, validateUploadFile } from './utils'
import type { UploadItem } from './useMediaUploads'

function UploadRow({
    item,
    onCancel,
    onRetry,
}: {
    item: UploadItem
    onCancel: () => void
    onRetry: () => void
}) {
    const percent = Math.round(item.progress * 100)

    return (
        <li
            className="rounded-lg border border-line px-3 py-2 text-sm dark:border-slate-700"
            data-testid="upload-item"
        >
            <div className="flex items-center justify-between gap-3">
                <span className="min-w-0 truncate font-medium">{item.file.name}</span>
                <span className="shrink-0 text-xs text-graphite dark:text-slate-500">
                    {item.status === 'queued' ? 'В очереди' : null}
                    {item.status === 'uploading' ? `${percent}%` : null}
                    {item.status === 'done'
                        ? item.asset?.duplicate === true
                            ? 'Уже в медиатеке'
                            : 'Загружен'
                        : null}
                    {item.status === 'error' ? 'Ошибка' : null}
                </span>
            </div>
            {item.status === 'uploading' || item.status === 'queued' ? (
                <div
                    className="mt-2 h-1.5 overflow-hidden rounded-full bg-line dark:bg-slate-800"
                    role="progressbar"
                    aria-label={`Загрузка ${item.file.name}`}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={percent}
                >
                    <div
                        className="h-full bg-brand-600 transition-all"
                        style={{ width: `${percent}%` }}
                    />
                </div>
            ) : null}
            {item.status === 'error' ? (
                <p className="mt-1 text-xs text-red-700 dark:text-red-400" role="alert">
                    {item.error}
                </p>
            ) : null}
            {item.status === 'done' &&
            item.asset !== null &&
            describeDuplicate(item.asset) !== null ? (
                <p
                    className="mt-1 text-xs text-amber-700 dark:text-amber-300"
                    data-testid="duplicate-note"
                >
                    {describeDuplicate(item.asset)}
                </p>
            ) : null}
            {item.status === 'uploading' || item.status === 'queued' || item.status === 'error' ? (
                <div className="mt-1 flex gap-2">
                    {item.status === 'error' && validateUploadFile(item.file) === null ? (
                        <Button type="button" size="sm" variant="ghost" onClick={onRetry}>
                            Повторить
                        </Button>
                    ) : null}
                    <Button type="button" size="sm" variant="ghost" onClick={onCancel}>
                        {item.status === 'error' ? 'Скрыть' : 'Отменить'}
                    </Button>
                </div>
            ) : null}
        </li>
    )
}

/** Список загрузок с прогрессом, ошибками и кнопкой очистки завершённых. */
export function MediaUploadList({
    items,
    onCancel,
    onRetry,
    onClearFinished,
}: {
    items: UploadItem[]
    onCancel: (id: string) => void
    onRetry: (id: string) => void
    onClearFinished: () => void
}) {
    const finishedUploads = items.filter(
        (item) => item.status === 'done' || item.status === 'error',
    ).length

    return (
        <section aria-label="Загрузки" className="space-y-2">
            <ul className="space-y-2">
                {items.map((item) => (
                    <UploadRow
                        key={item.id}
                        item={item}
                        onCancel={() => onCancel(item.id)}
                        onRetry={() => onRetry(item.id)}
                    />
                ))}
            </ul>
            {finishedUploads > 0 ? (
                <Button type="button" size="sm" variant="ghost" onClick={onClearFinished}>
                    Очистить список загрузок
                </Button>
            ) : null}
        </section>
    )
}
