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
import grapesjs from 'grapesjs'
import 'grapesjs/dist/css/grapes.min.css'
import { lazy, Suspense, useEffect, useMemo, useRef, useState } from 'react'
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
  const canvasRef = useRef<HTMLDivElement | null>(null)
  const editorRef = useRef<ReturnType<typeof grapesjs.init> | null>(null)
  const snapshotAppliedRef = useRef(false)
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(blocks[0]?.id ?? null)
  const [richTextDraft, setRichTextDraft] = useState(
    typeof blocks[0]?.content.richText === 'string'
      ? blocks[0].content.richText
      : '<p>Rich text для текущего блока</p>',
  )
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }))

  useEffect(() => {
    if (canvasRef.current === null || editorRef.current !== null) {
      return
    }

    const editor = grapesjs.init({
      container: canvasRef.current,
      fromElement: false,
      storageManager: false,
      height: '600px',
      panels: { defaults: [] },
      blockManager: { appendTo: '#gjs-block-registry' },
    })

    editorRef.current = editor

    editor.on('update', () => {
      onSnapshotChange({
        html: editor.getHtml(),
        css: editor.getCss() ?? '',
      })
    })

    return () => {
      editor.destroy()
      editorRef.current = null
      snapshotAppliedRef.current = false
    }
  }, [onSnapshotChange])

  useEffect(() => {
    if (editorRef.current === null || snapshotAppliedRef.current) {
      return
    }

    editorRef.current.setComponents(snapshot.html)
    editorRef.current.setStyle(snapshot.css)
    snapshotAppliedRef.current = true
  }, [snapshot.css, snapshot.html])

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

  return (
    <section className="space-y-4">
      <div className="rounded-xl border border-dashed border-slate-300 p-6 dark:border-slate-700">
        <p className="text-sm font-semibold">GrapesJS runtime container</p>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
          Страница `{pageId}`. Layout управляется GrapesJS, rich text блока редактируется через Tiptap bridge.
        </p>
      </div>

      <div className="grid gap-4 xl:grid-cols-[1fr_340px]">
        <div className="space-y-4">
          <div ref={canvasRef} className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800" />
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
            <div id="gjs-block-registry" className="mt-2 rounded-md border border-slate-200 p-2 dark:border-slate-700" />
            <ul className="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
              {blockRegistry.map((block) => (
                <li key={block.type}>
                  {block.title} ({block.category})
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
