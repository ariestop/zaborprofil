import { lazy, Suspense, useCallback, useState } from 'react'
import { useToast } from '../../app/providers/toast-provider'
import { previewPageBuilder } from '../../entities/page/api'
import { Button, PageLoadingState } from '../../shared/ui'
import { useCan } from '../../stores/auth'
import { useBuilderStore } from '../../modules/page-builder/state/builderStore'
import type { BuilderBlock } from '../../modules/page-builder/types'
import { createBlock, duplicateBlock, normalizePageBlocks, reorderBlocks } from '../../modules/page-builder/utils/pageBlocks'
import type { PageTemplateItem } from '../../types/api'
import { describeApiError } from '../seo/redirects/redirect-rules'
import { useAdvancedMode } from './advanced-mode'
import { SaveTemplateDialog, type SaveTemplateRequest } from './SaveTemplateDialog'
import { SectionTemplatesDialog } from './SectionTemplatesDialog'
import { templateToBlocks } from './template-blocks'

const PageBuilderContainer = lazy(async () => import('../../modules/page-builder/components/PageBuilderContainer').then((module) => ({ default: module.PageBuilderContainer })))

interface ContentTabProps {
  pageId: string
  pageType: string
}

export function ContentTab({ pageId, pageType }: ContentTabProps) {
  const { push } = useToast()
  const advanced = useAdvancedMode((state) => state.enabled)
  const canManageTemplates = useCan('pages.manage_templates')
  const [previewHtml, setPreviewHtml] = useState<string | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [sectionsOpen, setSectionsOpen] = useState(false)
  const [saveRequest, setSaveRequest] = useState<SaveTemplateRequest | null>(null)

  const blocks = useBuilderStore((state) => state.blocks)
  const selectedBlockId = useBuilderStore((state) => state.selectedBlockId)
  const validationIssues = useBuilderStore((state) => state.validationIssues)
  const setBlocks = useBuilderStore((state) => state.setBlocks)
  const selectBlock = useBuilderStore((state) => state.selectBlock)
  const updateBlock = useBuilderStore((state) => state.updateBlock)
  const deleteBlock = useBuilderStore((state) => state.deleteBlock)

  const handleAddBlock = useCallback((type: BuilderBlock['type']) => {
    setBlocks([...blocks, createBlock(type, blocks.length)], true)
  }, [blocks, setBlocks])

  const handleDuplicate = useCallback((blockId: string) => {
    const index = blocks.findIndex((block) => block.id === blockId)
    const block = blocks[index]
    if (block === undefined) {
      return
    }

    const duplicated = duplicateBlock(block, index + 1)
    setBlocks(normalizePageBlocks([
      ...blocks.slice(0, index + 1),
      duplicated,
      ...blocks.slice(index + 1),
    ]), true)
    selectBlock(duplicated.id)
  }, [blocks, selectBlock, setBlocks])

  const handleSaveBlockAsTemplate = useCallback((blockId: string) => {
    const block = blocks.find((item) => item.id === blockId)
    if (block !== undefined) {
      setSaveRequest({ kind: 'section', blocks: [block] })
    }
  }, [blocks])

  const handleInsertSection = useCallback((template: PageTemplateItem) => {
    const inserted = templateToBlocks(template, blocks.length)
    if (inserted.length === 0) {
      return
    }

    setBlocks(normalizePageBlocks([...blocks, ...inserted]), true)
    selectBlock(inserted[0]?.id ?? null)
    setSectionsOpen(false)
    push({ title: 'Секция добавлена', description: `Блоков: ${inserted.length}. Замените тексты-заготовки на свои.` })
  }, [blocks, push, selectBlock, setBlocks])

  const handleReorder = useCallback((sourceIndex: number, targetIndex: number) => {
    setBlocks(reorderBlocks(blocks, sourceIndex, targetIndex), true)
  }, [blocks, setBlocks])

  const handlePreview = useCallback(() => {
    setPreviewLoading(true)
    previewPageBuilder(pageId, blocks)
      .then((response) => setPreviewHtml(response.html))
      .catch((error: unknown) => {
        push({ title: 'Не удалось построить предпросмотр', description: describeApiError(error, 'Повторите попытку.') })
      })
      .finally(() => setPreviewLoading(false))
  }, [blocks, pageId, push])

  return (
    <Suspense fallback={<PageLoadingState />}>
      {canManageTemplates ? (
        <>
          <div className="mb-3 flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="outline" onClick={() => setSectionsOpen(true)}>Вставить секцию из шаблона</Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={blocks.length === 0}
              onClick={() => setSaveRequest({ kind: 'page', blocks })}
            >
              Сохранить страницу как шаблон
            </Button>
          </div>
          <SectionTemplatesDialog open={sectionsOpen} onClose={() => setSectionsOpen(false)} onInsert={handleInsertSection} />
          <SaveTemplateDialog request={saveRequest} pageType={pageType} onClose={() => setSaveRequest(null)} />
        </>
      ) : null}
      <PageBuilderContainer
        blocks={blocks}
        selectedBlockId={selectedBlockId}
        validationIssues={validationIssues}
        previewHtml={previewHtml}
        isPreviewLoading={previewLoading}
        showJson={advanced}
        onAddBlock={handleAddBlock}
        onSelectBlock={selectBlock}
        onReorderBlocks={handleReorder}
        onUpdateBlock={(block) => updateBlock(block.id, () => block)}
        onDeleteBlock={deleteBlock}
        onDuplicateBlock={handleDuplicate}
        onSaveBlockAsTemplate={canManageTemplates ? handleSaveBlockAsTemplate : undefined}
        onPreview={handlePreview}
      />
    </Suspense>
  )
}
