import { Button, Dialog } from '../../shared/ui'
import { EDIT_CONFLICT_MESSAGE, type EditConflict } from './edit-conflict'
import type { PageEditLockState } from './usePageEditLock'
import type { PendingDraftInfo } from './usePageEditorController'

function formatMoment(value: string | null): string {
  if (value === null) {
    return ''
  }

  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '' : date.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

interface EditLockBannerProps {
  lock: PageEditLockState
}

export function EditLockBanner({ lock }: EditLockBannerProps) {
  if (!lock.locked) {
    return null
  }

  const since = formatMoment(lock.since)
  const who = lock.holderIsSelf
    ? 'Эта страница уже открыта в другой вкладке или окне вашего аккаунта'
    : `Сейчас эту страницу редактирует ${lock.holderLabel ?? 'другой пользователь'}`

  return (
    <div
      role="status"
      data-testid="edit-lock-banner"
      className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200"
    >
      <p>
        {who}{since === '' ? '' : ` (с ${since})`}. Если сохранить блоки одновременно, одна из правок потребует разрешения конфликта.
      </p>
      <Button type="button" size="sm" variant="outline" onClick={() => void lock.takeOver()}>
        Редактировать здесь
      </Button>
    </div>
  )
}

interface DraftRestoreBannerProps {
  draft: PendingDraftInfo | null
  onRestore: () => void
  onDiscard: () => void
}

export function DraftRestoreBanner({ draft, onRestore, onDiscard }: DraftRestoreBannerProps) {
  if (draft === null) {
    return null
  }

  const savedAt = formatMoment(draft.savedAt)

  return (
    <div
      role="status"
      data-testid="draft-restore-banner"
      className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-700 dark:bg-sky-950/40 dark:text-sky-200"
    >
      <p>
        Найдены несохранённые изменения{savedAt === '' ? '' : ` от ${savedAt}`}, сохранённые в этом браузере.
        {draft.serverChanged ? ' После этого блоки страницы на сервере изменились — при сохранении понадобится разрешить конфликт.' : ''}
      </p>
      <div className="flex gap-2">
        <Button type="button" size="sm" variant="ghost" onClick={onDiscard}>Отбросить</Button>
        <Button type="button" size="sm" onClick={onRestore}>Восстановить</Button>
      </div>
    </div>
  )
}

interface EditConflictDialogProps {
  conflict: EditConflict | null
  onOverwrite: () => void
  onReload: () => void
  onDismiss: () => void
}

export function EditConflictDialog({ conflict, onOverwrite, onReload, onDismiss }: EditConflictDialogProps) {
  const changedAt = formatMoment(conflict?.serverUpdatedAt ?? null)

  return (
    <Dialog
      open={conflict !== null}
      onOpenChange={(open) => (!open ? onDismiss() : undefined)}
      title="Страница изменена другим пользователем"
      description={`${EDIT_CONFLICT_MESSAGE}${changedAt === '' ? '' : ` Страница обновлена ${changedAt}.`}`}
      closeOnInteractOutside={false}
    >
      <ul className="mb-4 list-disc space-y-1 pl-5 text-sm text-graphite dark:text-slate-300">
        <li><strong>Перезаписать</strong> — ваши блоки заменят версию на сервере.</li>
        <li><strong>Загрузить с сервера</strong> — ваши несохранённые правки блоков будут потеряны.</li>
        <li><strong>Остаться</strong> — закрыть окно и скопировать нужное вручную; черновик сохранён в браузере.</li>
      </ul>
      <div className="flex flex-wrap justify-end gap-2">
        <Button type="button" variant="ghost" onClick={onDismiss}>Остаться в редакторе</Button>
        <Button type="button" variant="outline" onClick={onReload}>Загрузить с сервера</Button>
        <Button type="button" variant="danger" onClick={onOverwrite}>Перезаписать моими правками</Button>
      </div>
    </Dialog>
  )
}
