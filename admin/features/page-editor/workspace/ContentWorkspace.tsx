import { useCallback, useMemo, useState } from 'react'
import { useToast } from '../../../app/providers/toast-provider'
import { useBuilderStore } from '../../../modules/page-builder/state/builderStore'
import type { BuilderBlock, BuilderBlockType } from '../../../modules/page-builder/types'
import { createBlock, duplicateBlock, normalizePageBlocks, reorderBlocks, validatePageBlocks } from '../../../modules/page-builder/utils/pageBlocks'
import { cn } from '../../../shared/lib/cn'
import { useCan } from '../../../stores/auth'
import type { PageTemplateItem } from '../../../types/api'
import { UndoToast, type UndoToastState } from '../../leads/UndoToast'
import { useAdvancedMode } from '../advanced-mode'
import { SaveTemplateDialog, type SaveTemplateRequest } from '../SaveTemplateDialog'
import { SectionTemplatesDialog } from '../SectionTemplatesDialog'
import { templateToBlocks } from '../template-blocks'
import { AddBlockDialog } from './AddBlockDialog'
import { BlockInspector } from './BlockInspector'
import { PageCanvas, type PreviewDevice } from './PageCanvas'
import { StructurePanel, type ReadinessItem } from './StructurePanel'
import { STARTER_CONTENT, blockTitle, isPlaceholderBlock, kindName, plural } from './block-kinds'

export interface ContentWorkspaceProps {
  pageType: string
  pageH1: string
  pagePath: string
  metaDescription: string
  ogImage: string
  onOpenSeo: () => void
}

type MobilePane = 'structure' | 'fields' | 'preview'

const MOBILE_PANES: Array<{ id: MobilePane, label: string }> = [
  { id: 'structure', label: 'Структура' },
  { id: 'fields', label: 'Поля' },
  { id: 'preview', label: 'Просмотр' },
]

interface DeletedBlock extends UndoToastState {
  block: BuilderBlock
  index: number
}

/** Вкладка «Контент»: структура блоков, страница как на сайте и поля выбранного блока. */
export default function ContentWorkspace({ pageType, pageH1, pagePath, metaDescription, ogImage, onOpenSeo }: ContentWorkspaceProps) {
  const { push } = useToast()
  const advanced = useAdvancedMode((state) => state.enabled)
  const canManageTemplates = useCan('pages.manage_templates')
  const blocks = useBuilderStore((state) => state.blocks)
  const selectedBlockId = useBuilderStore((state) => state.selectedBlockId)
  const setBlocks = useBuilderStore((state) => state.setBlocks)
  const selectBlock = useBuilderStore((state) => state.selectBlock)
  const updateBlock = useBuilderStore((state) => state.updateBlock)
  const deleteBlock = useBuilderStore((state) => state.deleteBlock)

  const [device, setDevice] = useState<PreviewDevice>('desktop')
  const [pane, setPane] = useState<MobilePane>('structure')
  const [addOpen, setAddOpen] = useState(false)
  /** Куда вставлять: id блока, после которого, или null — в конец страницы. */
  const [addAfter, setAddAfter] = useState<string | null>(null)
  const [sectionsOpen, setSectionsOpen] = useState(false)
  const [saveRequest, setSaveRequest] = useState<SaveTemplateRequest | null>(null)
  const [deleted, setDeleted] = useState<DeletedBlock | null>(null)

  const validation = useMemo(() => validatePageBlocks(blocks), [blocks])
  const invalidBlockIds = useMemo(() => new Set(validation.issues.map((issue) => issue.blockId)), [validation])
  const selectedBlock = blocks.find((block) => block.id === selectedBlockId) ?? null
  const selectedIssues = validation.issues.filter((issue) => issue.blockId === selectedBlockId)

  const select = useCallback((blockId: string) => {
    selectBlock(blockId)
    setPane((current) => (current === 'structure' || current === 'preview' ? 'fields' : current))
  }, [selectBlock])

  const commit = useCallback((next: BuilderBlock[], selectId?: string) => {
    setBlocks(normalizePageBlocks(next), true)
    if (selectId !== undefined) {
      selectBlock(selectId)
    }
  }, [selectBlock, setBlocks])

  const move = useCallback((blockId: string, direction: -1 | 1) => {
    const index = blocks.findIndex((block) => block.id === blockId)
    const target = index + direction
    if (index !== -1 && target >= 0 && target < blocks.length) {
      setBlocks(reorderBlocks(blocks, index, target), true)
    }
  }, [blocks, setBlocks])

  const duplicate = useCallback((blockId: string) => {
    const index = blocks.findIndex((block) => block.id === blockId)
    const block = blocks[index]
    if (block === undefined) {
      return
    }
    const copy = duplicateBlock({ ...block, name: `${blockTitle(block)} — копия` }, index + 1)
    commit([...blocks.slice(0, index + 1), copy, ...blocks.slice(index + 1)], copy.id)
  }, [blocks, commit])

  const toggleHidden = useCallback((blockId: string) => {
    updateBlock(blockId, (block) => ({ ...block, enabled: !block.enabled }))
  }, [updateBlock])

  const remove = useCallback((blockId: string) => {
    const index = blocks.findIndex((block) => block.id === blockId)
    const block = blocks[index]
    if (block === undefined) {
      return
    }
    deleteBlock(blockId)
    setDeleted({ id: Date.now(), text: `Блок «${blockTitle(block)}» удалён`, block, index })
  }, [blocks, deleteBlock])

  const undoDelete = () => {
    if (deleted === null) {
      return
    }
    const current = useBuilderStore.getState().blocks
    commit([...current.slice(0, deleted.index), deleted.block, ...current.slice(deleted.index)], deleted.block.id)
    setDeleted(null)
  }
  const dismissDeleted = useCallback(() => setDeleted(null), [])

  const insertAt = (after: string | null) => {
    const index = after === null ? blocks.length : blocks.findIndex((block) => block.id === after) + 1

    return index <= 0 && after !== null ? blocks.length : index
  }

  const add = (type: BuilderBlockType) => {
    const index = insertAt(addAfter)
    const created = createBlock(type, index)
    const block = { ...created, name: kindName(type), content: { ...created.content, ...STARTER_CONTENT[type] } }
    commit([...blocks.slice(0, index), block, ...blocks.slice(index)], block.id)
    setAddOpen(false)
    setPane('fields')
  }

  const insertSection = (template: PageTemplateItem) => {
    const index = insertAt(addAfter)
    const inserted = templateToBlocks(template, index)
    if (inserted.length === 0) {
      return
    }
    commit([...blocks.slice(0, index), ...inserted, ...blocks.slice(index)], inserted[0]?.id)
    setSectionsOpen(false)
    push({ title: 'Секция добавлена', description: `Блоков: ${inserted.length}. Замените тексты-заготовки на свои.` })
  }

  const openAdd = (after: string | null) => {
    setAddAfter(after)
    setAddOpen(true)
  }

  const showFirstDraft = () => {
    const first = blocks.find((block) => block.enabled && isPlaceholderBlock(block))
    if (first !== undefined) {
      select(first.id)
    }
  }

  const showFirstInvalid = () => {
    const first = blocks.find((block) => invalidBlockIds.has(block.id))
    if (first !== undefined) {
      select(first.id)
    }
  }

  const drafts = blocks.filter((block) => block.enabled && isPlaceholderBlock(block)).length
  const readiness: ReadinessItem[] = [
    drafts > 0
      ? { id: 'drafts', ok: false, text: `Заменить заготовки: ${drafts} ${plural(drafts, 'блок', 'блока', 'блоков')}`, action: { label: 'Показать', onClick: showFirstDraft } }
      : { id: 'drafts', ok: true, text: blocks.length === 0 ? 'Добавьте блоки на страницу' : 'Заготовки заменены' },
    ...(invalidBlockIds.size > 0 ? [{ id: 'errors', ok: false, text: `Ошибки в ${invalidBlockIds.size} ${plural(invalidBlockIds.size, 'блоке', 'блоках', 'блоках')}`, action: { label: 'Показать', onClick: showFirstInvalid } }] : []),
    { id: 'description', ok: metaDescription.trim() !== '', text: metaDescription.trim() !== '' ? 'Описание для поиска заполнено' : 'Нет описания для поиска', action: { label: 'SEO', onClick: onOpenSeo } },
    { id: 'og', ok: ogImage.trim() !== '', text: ogImage.trim() !== '' ? 'Картинка для соцсетей выбрана' : 'Нет картинки для соцсетей', action: { label: 'SEO', onClick: onOpenSeo } },
  ]
  if (blocks.length === 0) {
    readiness[0] = { id: 'drafts', ok: false, text: 'Добавьте блоки на страницу', action: { label: 'Добавить', onClick: () => openAdd(null) } }
  }

  const afterBlock = addAfter === null ? null : blocks.find((block) => block.id === addAfter) ?? null
  const placement = afterBlock === null ? 'Вставим в конец страницы' : `Вставим после блока «${blockTitle(afterBlock)}»`
  const panel = 'min-w-0 rounded-[14px] border border-line bg-white p-4 dark:border-slate-800 dark:bg-slate-900'

  return (
    <div className="flex flex-col gap-4">
      <div role="group" aria-label="Что показать" className="flex gap-0.5 rounded-xl bg-line p-[3px] xl:hidden dark:bg-slate-800">
        {MOBILE_PANES.map((item) => (
          <button
            key={item.id}
            type="button"
            aria-pressed={pane === item.id}
            onClick={() => setPane(item.id)}
            className={cn('h-10 flex-1 rounded-[10px] text-sm font-semibold', pane === item.id ? 'bg-white text-ink shadow-xs dark:bg-slate-900 dark:text-slate-100' : 'text-graphite dark:text-slate-400')}
          >
            {item.label}
          </button>
        ))}
      </div>

      <div className="grid grid-cols-[minmax(0,1fr)] items-start gap-5 xl:grid-cols-[290px_minmax(0,1fr)_380px]">
        <div className={cn(panel, pane === 'structure' ? 'block' : 'hidden xl:block')}>
          <StructurePanel
            blocks={blocks}
            selectedBlockId={selectedBlockId}
            invalidBlockIds={invalidBlockIds}
            readiness={readiness}
            onSelect={select}
            onReorder={(source, target) => setBlocks(reorderBlocks(blocks, source, target), true)}
            onAdd={() => openAdd(null)}
            onShowDrafts={showFirstDraft}
          />
        </div>

        <div className={cn('min-w-0', pane === 'preview' ? 'block' : 'hidden xl:block')}>
          <PageCanvas
            blocks={blocks}
            selectedBlockId={selectedBlockId}
            pageH1={pageH1}
            pagePath={pagePath}
            device={device}
            onDeviceChange={setDevice}
            onSelect={select}
            onMove={move}
            onDuplicate={duplicate}
            onToggleHidden={toggleHidden}
            onDelete={remove}
            onInsertAfter={openAdd}
          />
        </div>

        <div className={cn(panel, 'xl:sticky xl:top-20 xl:max-h-[calc(100vh-6rem)] xl:overflow-y-auto', pane === 'fields' ? 'block' : 'hidden xl:block')}>
          <BlockInspector
            key={selectedBlock?.id ?? 'none'}
            block={selectedBlock}
            issues={selectedIssues}
            showJson={advanced}
            onChange={(next) => updateBlock(next.id, () => next)}
            onDuplicate={() => { if (selectedBlock !== null) duplicate(selectedBlock.id) }}
            onDelete={() => { if (selectedBlock !== null) remove(selectedBlock.id) }}
            onSaveAsSection={canManageTemplates && selectedBlock !== null ? () => setSaveRequest({ kind: 'section', blocks: [selectedBlock] }) : undefined}
          />
        </div>
      </div>

      <AddBlockDialog
        open={addOpen}
        placement={placement}
        onOpenChange={setAddOpen}
        onAdd={add}
        onInsertSection={canManageTemplates ? () => { setAddOpen(false); setSectionsOpen(true) } : undefined}
      />
      {canManageTemplates ? (
        <>
          <SectionTemplatesDialog open={sectionsOpen} onClose={() => setSectionsOpen(false)} onInsert={insertSection} />
          <SaveTemplateDialog request={saveRequest} pageType={pageType} onClose={() => setSaveRequest(null)} />
        </>
      ) : null}
      <UndoToast toast={deleted} onUndo={undoDelete} onDismiss={dismissDeleted} />
    </div>
  )
}
