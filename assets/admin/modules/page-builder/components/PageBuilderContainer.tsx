import {
  DndContext,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { lazy, Suspense, useMemo, useState } from 'react'
import { Button } from '../../../shared/ui'
import type { BuilderBlockDefinition, BuilderBlockItem, BuilderSnapshot, BuilderVersionRecord } from '../types'

const RichTextEditor = lazy(async () => import('../../../features/rich-text/RichTextEditor').then((module) => ({ default: module.RichTextEditor })))

interface PageBuilderContainerProps {
  pageId: string
  versions: BuilderVersionRecord[]
  blockRegistry: BuilderBlockDefinition[]
  blocks: BuilderBlockItem[]
  snapshot: BuilderSnapshot
  previewUrl: string | null
  onSnapshotChange: (snapshot: BuilderSnapshot) => void
  onReorderBlocks: (blockIds: string[]) => Promise<void>
  onSaveRichText: (blockId: string, richText: string) => Promise<void>
}

function SortableBlockRow({
  block,
  isSelected,
  onSelect,
}: {
  block: BuilderBlockItem
  isSelected: boolean
  onSelect: () => void
}) {
  const { attributes, listeners, setNodeRef, transform, transition } = useSortable({
    id: block.id,
  })

  return (
    <button
      ref={setNodeRef}
      type="button"
      onClick={onSelect}
      data-testid={`builder-block-row-${block.id}`}
      style={{
        transform: CSS.Transform.toString(transform),
        transition,
      }}
      className={[
        'w-full cursor-grab rounded-lg border px-3 py-2 text-left text-sm',
        isSelected
          ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
          : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900',
      ].join(' ')}
      {...attributes}
      {...listeners}
    >
      {block.name}
    </button>
  )
}

export function PageBuilderContainer({
  pageId,
  versions,
  blockRegistry,
  blocks,
  snapshot,
  previewUrl,
  onSnapshotChange,
  onReorderBlocks,
  onSaveRichText,
}: PageBuilderContainerProps) {
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(blocks[0]?.id ?? null)
  const [richTextDraft, setRichTextDraft] = useState(
    typeof blocks[0]?.content.richText === 'string'
      ? blocks[0].content.richText
      : '<p>Rich text для текущего блока</p>',
  )
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }))

  const selectedBlock = useMemo(() => {
    if (selectedBlockId === null) {
      return blocks[0]
    }

    return blocks.find((block) => block.id === selectedBlockId) ?? blocks[0]
  }, [blocks, selectedBlockId])

  const richTextValue = typeof selectedBlock?.content.richText === 'string'
    ? selectedBlock.content.richText
    : '<p>Rich text для текущего блока</p>'

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event
    if (over === null || active.id === over.id) {
      return
    }

    const oldIndex = blocks.findIndex((item) => item.id === active.id)
    const newIndex = blocks.findIndex((item) => item.id === over.id)
    if (oldIndex === -1 || newIndex === -1) {
      return
    }

    const reordered = [...blocks]
    const [moved] = reordered.splice(oldIndex, 1)
    if (moved === undefined) {
      return
    }
    reordered.splice(newIndex, 0, moved)

    await onReorderBlocks(reordered.map((item) => item.id))
  }

  const updateSnapshotField = (field: 'html' | 'css', value: string) => {
    onSnapshotChange({
      ...snapshot,
      [field]: value,
    })
  }

  return (
    <section className="space-y-4">
      <div className="rounded-xl border border-dashed border-slate-300 p-6 dark:border-slate-700">
        <p className="text-sm font-semibold">Builder snapshot editor</p>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
          Страница `{pageId}`. Layout хранится в snapshot HTML/CSS, rich text блока редактируется через Tiptap bridge.
        </p>
      </div>

      <div className="grid gap-4 xl:grid-cols-[1fr_340px]">
        <div className="space-y-4">
          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
            <h3 className="text-sm font-semibold">Canvas HTML</h3>
            <textarea
              value={snapshot.html}
              onChange={(event) => {
                updateSnapshotField('html', event.currentTarget.value)
              }}
              className="mt-2 min-h-56 w-full rounded-md border border-slate-300 bg-white px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-900"
              data-testid="builder-html-editor"
            />
          </div>
          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
            <h3 className="text-sm font-semibold">Canvas CSS</h3>
            <textarea
              value={snapshot.css}
              onChange={(event) => {
                updateSnapshotField('css', event.currentTarget.value)
              }}
              className="mt-2 min-h-40 w-full rounded-md border border-slate-300 bg-white px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-900"
              data-testid="builder-css-editor"
            />
          </div>
          <div className="flex items-center gap-2">
            {previewUrl !== null ? (
              <a
                href={previewUrl}
                target="_blank"
                rel="noreferrer"
                className="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
              >
                Preview
              </a>
            ) : null}
          </div>
        </div>

        <div className="space-y-4">
          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
            <h3 className="text-sm font-semibold">Block registry</h3>
            <ul className="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
              {blockRegistry.map((block) => (
                <li key={block.type}>
                  {block.category}: {block.title}
                </li>
              ))}
            </ul>
          </div>

          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
            <h3 className="text-sm font-semibold">DnD blocks (backend reorder)</h3>
            <div className="mt-3">
              <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                <SortableContext items={blocks.map((block) => block.id)} strategy={verticalListSortingStrategy}>
                  <div className="space-y-2">
                    {blocks.map((block) => (
                      <SortableBlockRow
                        key={block.id}
                        block={block}
                        isSelected={block.id === selectedBlockId}
                        onSelect={() => {
                          setSelectedBlockId(block.id)
                          setRichTextDraft(
                            typeof block.content.richText === 'string'
                              ? block.content.richText
                              : '<p>Rich text для текущего блока</p>',
                          )
                        }}
                      />
                    ))}
                  </div>
                </SortableContext>
              </DndContext>
            </div>
          </div>

          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
            <h3 className="text-sm font-semibold">Tiptap bridge (block rich text)</h3>
            <div className="mt-3">
              <Suspense fallback={<p className="text-sm text-slate-500 dark:text-slate-400">Загрузка Tiptap...</p>}>
                <RichTextEditor
                  key={selectedBlock?.id ?? 'none'}
                  initialValue={richTextValue}
                  onChange={setRichTextDraft}
                />
              </Suspense>
            </div>
            {selectedBlock !== undefined ? (
              <div className="mt-3">
                <Button
                  type="button"
                  size="sm"
                  data-testid="builder-save-richtext"
                  onClick={() => {
                    void onSaveRichText(selectedBlock.id, richTextDraft)
                  }}
                >
                  Сохранить rich text в block settings
                </Button>
              </div>
            ) : null}
          </div>

          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
            <h3 className="text-sm font-semibold">Versioning hooks</h3>
            <ul className="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
              {versions.map((version) => (
                <li key={version.id}>
                  {version.createdAt} — {version.comment}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </section>
  )
}
