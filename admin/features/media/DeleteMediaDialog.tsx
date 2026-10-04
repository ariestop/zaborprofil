import { useState } from 'react'
import { useMediaUsagesQuery } from '../../entities/media/api'
import { Button, Dialog } from '../../shared/ui'
import type { MediaAssetItem } from '../../types/api'
import { MediaUsageList } from './MediaUsageList'
import { describeMediaError, formatUsageCount } from './utils'

interface DeleteMediaDialogProps {
  asset: MediaAssetItem | null
  pending: boolean
  onCancel: () => void
  onConfirm: (force: boolean) => void
}

export function DeleteMediaDialog({ asset, pending, onCancel, onConfirm }: DeleteMediaDialogProps) {
  const usagesQuery = useMediaUsagesQuery(asset?.id ?? null)
  const [acknowledgedId, setAcknowledgedId] = useState<string | null>(null)
  const acknowledged = asset !== null && acknowledgedId === asset.id

  const usages = usagesQuery.data
  const used = (usages?.total ?? 0) > 0
  const canConfirm = !pending && usagesQuery.isSuccess && (!used || acknowledged)

  return (
    <Dialog
      open={asset !== null}
      onOpenChange={(open) => (!open ? onCancel() : undefined)}
      title={used ? 'Файл используется на сайте' : 'Удалить файл?'}
      description={asset === null ? undefined : `«${asset.originalName}» будет удалён вместе с вариантами.`}
      contentClassName="max-w-xl"
    >
      <div className="space-y-3" data-testid="delete-media-dialog">
        {usagesQuery.isLoading ? <p className="text-sm text-slate-500">Проверяем, где используется файл…</p> : null}
        {usagesQuery.isError ? (
          <p className="text-sm text-red-700 dark:text-red-400" role="alert">
            Не удалось проверить использование файла: {describeMediaError(usagesQuery.error)}
          </p>
        ) : null}

        {usagesQuery.isSuccess && !used ? <p className="text-sm text-slate-600 dark:text-slate-300">Файл нигде не используется — его можно удалить безопасно.</p> : null}

        {usagesQuery.isSuccess && used && usages !== undefined ? (
          <>
            <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-900/20 dark:text-amber-200" role="alert">
              Файл используется в {formatUsageCount(usages.total)}. После удаления изображения на этих страницах перестанут отображаться.
            </p>
            <MediaUsageList usages={usages.usages} className="max-h-56 space-y-1.5 overflow-y-auto" />
            <label className="flex items-start gap-2 text-sm">
              <input type="checkbox" className="mt-0.5" checked={acknowledged} onChange={(event) => setAcknowledgedId(event.target.checked && asset !== null ? asset.id : null)} />
              <span>Я понимаю, что файл пропадёт со страниц из списка, и хочу удалить его всё равно</span>
            </label>
          </>
        ) : null}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onCancel}>Отмена</Button>
          <Button type="button" variant="danger" disabled={!canConfirm} onClick={() => onConfirm(used)}>
            {used ? 'Удалить всё равно' : 'Удалить'}
          </Button>
        </div>
      </div>
    </Dialog>
  )
}
