import { Dialog } from '../../shared/ui'
import type { MediaAssetItem } from '../../types/api'
import { MediaLibrary } from './MediaLibrary'

interface MediaPickerDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSelect: (asset: MediaAssetItem) => void
  imagesOnly?: boolean
  title?: string
}

export function MediaPickerDialog({ open, onOpenChange, onSelect, imagesOnly = true, title = 'Выбор из медиатеки' }: MediaPickerDialogProps) {
  return (
    <Dialog
      open={open}
      onOpenChange={onOpenChange}
      title={title}
      description="Выберите файл или загрузите новый: перетащите его в окно либо нажмите «Загрузить файлы»."
      contentClassName="max-w-5xl max-h-[90vh] overflow-y-auto"
    >
      {open ? (
        <MediaLibrary
          mode="select"
          imagesOnly={imagesOnly}
          perPage={12}
          onSelect={(asset) => {
            onSelect(asset)
            onOpenChange(false)
          }}
        />
      ) : null}
    </Dialog>
  )
}
