import type { BuilderBlock } from '../types'
import { BlockEditorPanel } from './BlockEditorPanel'
import { BlockList } from './BlockList'
import { BlockPreview } from './BlockPreview'
import { BuilderSidebar } from './BuilderSidebar'
import { BuilderTopbar } from './BuilderTopbar'
import { BuilderValidationErrors } from './BuilderValidationErrors'
import { EmptyBuilderState } from './EmptyBuilderState'
import { PagePreview } from './PagePreview'

interface PageBuilderContainerProps {
  blocks: BuilderBlock[]
  selectedBlockId: string | null
  dirty: boolean
  validationIssues: Array<{ blockId: string; path: string; message: string }>
  previewHtml: string | null
  isSaving: boolean
  onAddBlock: (type: BuilderBlock['type']) => void
  onReorderBlocks: (sourceIndex: number, targetIndex: number) => void
  onSelectBlock: (blockId: string) => void
  onUpdateBlock: (block: BuilderBlock) => void
  onDeleteBlock: (blockId: string) => void
  onDuplicateBlock: (blockId: string) => void
  onSave: () => void
  onPreview: () => void
  onPublish: () => void
}

export function PageBuilderContainer({
  blocks,
  selectedBlockId,
  dirty,
  validationIssues,
  previewHtml,
  isSaving,
  onAddBlock,
  onReorderBlocks,
  onSelectBlock,
  onUpdateBlock,
  onDeleteBlock,
  onDuplicateBlock,
  onSave,
  onPreview,
  onPublish,
}: PageBuilderContainerProps) {
  const selectedBlock = selectedBlockId === null
    ? null
    : blocks.find((block) => block.id === selectedBlockId) ?? null

  return (
    <section className="space-y-4">
      <BuilderTopbar dirty={dirty} isSaving={isSaving} onSave={onSave} onPreview={onPreview} onPublish={onPublish} />
      <BuilderValidationErrors issues={validationIssues} />
      <div className="grid gap-4 xl:grid-cols-[280px_1fr_380px]">
        <BuilderSidebar onAddBlock={onAddBlock} />
        <div className="space-y-4">
          {blocks.length === 0 ? (
            <EmptyBuilderState onOpenCatalog={() => undefined} />
          ) : (
            <BlockList
              blocks={blocks}
              selectedBlockId={selectedBlockId}
              onSelectBlock={onSelectBlock}
              onReorder={onReorderBlocks}
            />
          )}
          <PagePreview previewHtml={previewHtml} />
        </div>
        <div className="space-y-4">
          <BlockEditorPanel
            block={selectedBlock}
            onUpdate={onUpdateBlock}
            onDelete={onDeleteBlock}
            onDuplicate={onDuplicateBlock}
          />
          <BlockPreview block={selectedBlock} />
        </div>
      </div>
    </section>
  )
}
