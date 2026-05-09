import { Button } from './button'
import { Dialog } from './dialog'

interface ConfirmDialogProps {
  open: boolean
  title: string
  description?: string
  confirmLabel?: string
  cancelLabel?: string
  onConfirm: () => void
  onCancel: () => void
}

export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel = 'Подтвердить',
  cancelLabel = 'Отмена',
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  return (
    <Dialog open={open} onOpenChange={(nextOpen) => (!nextOpen ? onCancel() : undefined)} title={title} description={description}>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="ghost" onClick={onCancel}>{cancelLabel}</Button>
        <Button type="button" variant="danger" onClick={onConfirm}>{confirmLabel}</Button>
      </div>
    </Dialog>
  )
}
