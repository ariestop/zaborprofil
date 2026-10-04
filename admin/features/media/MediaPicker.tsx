import { useId, useState } from 'react'
import { Button, Input } from '../../shared/ui'
import type { MediaAssetItem } from '../../types/api'
import { MediaPickerDialog } from './MediaPickerDialog'

interface MediaPickerProps {
  value: string
  onChange: (value: string, asset?: MediaAssetItem) => void
  label: string
  description?: string
  imagesOnly?: boolean
  absoluteUrl?: boolean
  allowManualInput?: boolean
  className?: string
}

export function resolveMediaValue(asset: Pick<MediaAssetItem, 'publicPath'>, absoluteUrl: boolean): string {
  return absoluteUrl ? `${window.location.origin}${asset.publicPath}` : asset.publicPath
}

export function MediaPicker({
  value,
  onChange,
  label,
  description,
  imagesOnly = true,
  absoluteUrl = false,
  allowManualInput = true,
  className,
}: MediaPickerProps) {
  const inputId = useId()
  const [open, setOpen] = useState(false)
  const hasValue = value.trim() !== ''
  const showPreview = hasValue && imagesOnly

  return (
    <div className={className} data-testid="media-picker">
      <label htmlFor={inputId} className="block text-xs font-medium text-slate-700 dark:text-slate-200">{label}</label>
      <div className="mt-1 flex items-start gap-3">
        {showPreview ? (
          <img src={value} alt="" className="h-16 w-24 shrink-0 rounded-md border border-slate-200 bg-slate-100 object-cover dark:border-slate-700 dark:bg-slate-800" />
        ) : null}
        <div className="min-w-0 flex-1 space-y-2">
          <Input
            id={inputId}
            value={value}
            readOnly={!allowManualInput}
            placeholder="Файл не выбран"
            onChange={(event) => onChange(event.target.value)}
          />
          <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="outline" onClick={() => setOpen(true)}>Выбрать из медиатеки</Button>
            {hasValue ? <Button type="button" size="sm" variant="ghost" onClick={() => onChange('')}>Очистить</Button> : null}
          </div>
        </div>
      </div>
      {description !== undefined ? <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{description}</p> : null}
      <MediaPickerDialog
        open={open}
        onOpenChange={setOpen}
        imagesOnly={imagesOnly}
        onSelect={(asset) => onChange(resolveMediaValue(asset, absoluteUrl), asset)}
      />
    </div>
  )
}
