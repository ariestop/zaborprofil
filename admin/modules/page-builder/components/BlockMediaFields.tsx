import { useMemo, useState } from 'react'
import { MediaPicker } from '../../../features/media/MediaPicker'
import { MediaPickerDialog } from '../../../features/media/MediaPickerDialog'
import { Button } from '../../../shared/ui'
import {
  appendMediaItem,
  applyMediaSelection,
  canAppendMediaItem,
  findMediaFields,
} from '../blocks/shared/mediaFields'
import type { BuilderBlock } from '../types'

interface BlockMediaFieldsProps {
  block: BuilderBlock
  onUpdate: (nextBlock: BuilderBlock) => void
}

export function BlockMediaFields({ block, onUpdate }: BlockMediaFieldsProps) {
  const [appendOpen, setAppendOpen] = useState(false)
  const fields = useMemo(() => findMediaFields(block.content, block.type), [block.content, block.type])
  const canAppend = canAppendMediaItem(block.type)

  if (fields.length === 0 && !canAppend) {
    return null
  }

  const updateContent = (content: Record<string, unknown>): void => {
    onUpdate({
      ...block,
      content,
      metadata: { ...block.metadata, updatedAt: new Date().toISOString() },
    })
  }

  return (
    <section className="space-y-3 rounded-md border border-slate-200 p-3 dark:border-slate-700" aria-label="Изображения блока" data-testid="block-media-fields">
      <p className="text-xs font-medium uppercase text-slate-500">Изображения</p>
      {fields.map((field) => (
        <MediaPicker
          key={field.path.join('.')}
          label={field.label}
          value={field.value}
          onChange={(value, asset) => updateContent(applyMediaSelection(block.content, field, value, asset))}
        />
      ))}
      {canAppend ? (
        <>
          <Button type="button" size="sm" variant="outline" onClick={() => setAppendOpen(true)}>Добавить изображение из медиатеки</Button>
          <MediaPickerDialog
            open={appendOpen}
            onOpenChange={setAppendOpen}
            onSelect={(asset) => updateContent(appendMediaItem(block.content, block.type, asset))}
          />
        </>
      ) : null}
    </section>
  )
}
