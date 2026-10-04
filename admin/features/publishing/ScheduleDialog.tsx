import { useState } from 'react'
import { Button } from '../../shared/ui/button'
import { Dialog } from '../../shared/ui/dialog'
import { fromDateTimeLocal, toDateTimeLocal } from './datetime'
import type { ScheduleInput } from './api'
import type { WorkflowStatus } from './types'

interface ScheduleDialogProps {
  open: boolean
  status: WorkflowStatus
  initialPublishAt: string | null
  initialUnpublishAt: string | null
  pending: boolean
  error: string | null
  onClose: () => void
  onSubmit: (input: ScheduleInput) => void
}

export function ScheduleDialog(props: ScheduleDialogProps) {
  return (
    <Dialog
      open={props.open}
      onOpenChange={(open) => { if (!open) props.onClose() }}
      title={props.status === 'published' ? 'Запланировать снятие с публикации' : 'Запланировать публикацию'}
      description="Время указывается в вашем часовом поясе. Публикацию и снятие выполняет планировщик (раз в минуту)."
    >
      <ScheduleForm {...props} />
    </Dialog>
  )
}

function ScheduleForm({ status, initialPublishAt, initialUnpublishAt, pending, error, onClose, onSubmit }: ScheduleDialogProps) {
  const unpublishOnly = status === 'published'
  const [publishAt, setPublishAt] = useState(toDateTimeLocal(initialPublishAt))
  const [unpublishAt, setUnpublishAt] = useState(toDateTimeLocal(initialUnpublishAt))
  const [comment, setComment] = useState('')

  const publishIso = fromDateTimeLocal(publishAt)
  const unpublishIso = fromDateTimeLocal(unpublishAt)
  const missingRequired = unpublishOnly ? unpublishIso === null : publishIso === null
  const invalidOrder = !unpublishOnly && publishIso !== null && unpublishIso !== null && unpublishIso <= publishIso

  return (
    <form
      className="mt-4 space-y-4"
      onSubmit={(event) => {
        event.preventDefault()
        if (missingRequired || invalidOrder) return
        onSubmit({ publishAt: unpublishOnly ? null : publishIso, unpublishAt: unpublishIso, comment: comment.trim() === '' ? null : comment.trim() })
      }}
    >
      {!unpublishOnly && (
        <label className="block text-sm font-medium text-slate-700">
          Опубликовать
          <input type="datetime-local" required value={publishAt} onChange={(event) => setPublishAt(event.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
        </label>
      )}
      <label className="block text-sm font-medium text-slate-700">
        {unpublishOnly ? 'Снять с публикации' : 'Снять с публикации (необязательно)'}
        <input type="datetime-local" required={unpublishOnly} value={unpublishAt} onChange={(event) => setUnpublishAt(event.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
      </label>
      <label className="block text-sm font-medium text-slate-700">
        Комментарий
        <input type="text" value={comment} onChange={(event) => setComment(event.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
      </label>
      {invalidOrder && <p role="alert" className="text-sm text-red-700">Снятие должно быть позже публикации.</p>}
      {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onClose}>Отмена</Button>
        <Button type="submit" disabled={pending || missingRequired || invalidOrder}>{pending ? 'Сохранение...' : 'Запланировать'}</Button>
      </div>
    </form>
  )
}
