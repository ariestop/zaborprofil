import { Button } from '../../../shared/ui'

interface BlockToolbarProps {
  enabled: boolean
  onToggle: () => void
  onDuplicate: () => void
  onDelete: () => void
}

export function BlockToolbar({ enabled, onToggle, onDuplicate, onDelete }: BlockToolbarProps) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <Button type="button" size="sm" variant="outline" onClick={onToggle}>
        {enabled ? 'Выключить' : 'Включить'}
      </Button>
      <Button type="button" size="sm" variant="outline" onClick={onDuplicate}>
        Дублировать
      </Button>
      <Button type="button" size="sm" variant="danger" onClick={onDelete}>
        Удалить
      </Button>
    </div>
  )
}
