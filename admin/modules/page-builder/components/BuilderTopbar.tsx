import { Button } from '../../../shared/ui'

interface BuilderTopbarProps {
  isPreviewLoading: boolean
  onPreview: () => void
}

export function BuilderTopbar({ isPreviewLoading, onPreview }: BuilderTopbarProps) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
      <p className="text-sm text-slate-600 dark:text-slate-300">
        Сохранение, публикация и предпросмотр страницы — в шапке редактора.
      </p>
      <Button type="button" size="sm" variant="outline" onClick={onPreview} disabled={isPreviewLoading}>
        {isPreviewLoading ? 'Обновление…' : 'Быстрый предпросмотр блоков'}
      </Button>
    </div>
  )
}
