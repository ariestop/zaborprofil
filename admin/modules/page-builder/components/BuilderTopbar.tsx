import { Button } from '../../../shared/ui'

interface BuilderTopbarProps {
  dirty: boolean
  isSaving: boolean
  onSave: () => void
  onPublish: () => void
  onPreview: () => void
}

export function BuilderTopbar({ dirty, isSaving, onSave, onPublish, onPreview }: BuilderTopbarProps) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
      <div className="text-sm">
        Статус: {dirty ? <span className="text-amber-600">есть несохраненные изменения</span> : <span className="text-emerald-600">синхронизировано</span>}
      </div>
      <div className="flex items-center gap-2">
        <Button type="button" size="sm" variant="outline" onClick={onPreview}>
          Preview
        </Button>
        <Button type="button" size="sm" variant="outline" onClick={onSave} disabled={isSaving}>
          {isSaving ? 'Saving...' : 'Save'}
        </Button>
        <Button type="button" size="sm" onClick={onPublish}>
          Publish
        </Button>
      </div>
    </div>
  )
}
