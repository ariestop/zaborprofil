import { DndContext, PointerSensor, closestCenter, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core'
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable'
import type { BuilderBlock } from '../types'
import { SortableBlockItem } from './SortableBlockItem'

interface BlockListProps {
  blocks: BuilderBlock[]
  selectedBlockId: string | null
  onSelectBlock: (blockId: string) => void
  onReorder: (sourceIndex: number, targetIndex: number) => void
}

export function BlockList({ blocks, selectedBlockId, onSelectBlock, onReorder }: BlockListProps) {
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }))

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event
    if (over === null || active.id === over.id) {
      return
    }

    const sourceIndex = blocks.findIndex((block) => block.id === active.id)
    const targetIndex = blocks.findIndex((block) => block.id === over.id)
    if (sourceIndex === -1 || targetIndex === -1) {
      return
    }

    onReorder(sourceIndex, targetIndex)
  }

  return (
    <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
      <h3 className="text-sm font-semibold">Блоки страницы</h3>
      <div className="mt-3">
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
          <SortableContext items={blocks.map((block) => block.id)} strategy={verticalListSortingStrategy}>
            <div className="space-y-2">
              {blocks.map((block) => (
                <SortableBlockItem
                  key={block.id}
                  block={block}
                  selected={block.id === selectedBlockId}
                  onSelect={() => onSelectBlock(block.id)}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      </div>
    </div>
  )
}
