import { EmptyState } from '../../../shared/ui'

interface EmptyBuilderStateProps {
  onOpenCatalog: () => void
}

export function EmptyBuilderState({ onOpenCatalog }: EmptyBuilderStateProps) {
  return (
    <div className="space-y-3">
      <EmptyState
        title="Страница пока пустая"
        description="Добавьте первый блок из каталога, чтобы начать сборку страницы."
      />
      <button
        type="button"
        className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700"
        onClick={onOpenCatalog}
      >
        Перейти к каталогу
      </button>
    </div>
  )
}
