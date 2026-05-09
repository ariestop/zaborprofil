import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import type { BuilderBlock } from '../types'

interface SortableBlockItemProps {
  block: BuilderBlock
  selected: boolean
  onSelect: () => void
}

export function SortableBlockItem({ block, selected, onSelect }: SortableBlockItemProps) {
  const { attributes, listeners, setNodeRef, transform, transition } = useSortable({ id: block.id })

  return (
    <button
      ref={setNodeRef}
      type="button"
      onClick={onSelect}
      className={[
        'w-full rounded-lg border px-3 py-2 text-left text-sm',
        selected ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' : 'border-slate-200 dark:border-slate-700',
      ].join(' ')}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      {...attributes}
      {...listeners}
    >
      <div className="font-medium">{block.type}</div>
      <div className="text-xs text-slate-500 dark:text-slate-400">position: {block.position}</div>
    </button>
  )
}
