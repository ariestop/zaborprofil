import { lazy, Suspense, useCallback, useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { PageHeader, Card, ErrorState, PageLoadingState } from '../shared/ui'
import type { BuilderBlock } from '../modules/page-builder/types'
import { useBuilderAutosave } from '../modules/page-builder/hooks/useBuilderAutosave'
import {
  previewPageBuilder,
  usePageBuilderQuery,
  usePageBuilderVersionsQuery,
  usePublishPageBuilderMutation,
  useRollbackPageBuilderMutation,
  useSavePageBuilderMutation,
} from '../entities/page/api'
import { useToast } from '../app/providers/toast-provider'
import { useBuilderStore } from '../modules/page-builder/state/builderStore'
import { createBlock, duplicateBlock, normalizePageBlocks, reorderBlocks, validatePageBlocks } from '../modules/page-builder/utils/pageBlocks'
import { UnsavedChangesGuard } from '../modules/page-builder/components/UnsavedChangesGuard'

const PageBuilderContainer = lazy(async () => import('../modules/page-builder/components/PageBuilderContainer').then((module) => ({ default: module.PageBuilderContainer })))

export default function PageBuilderPage() {
  const { id = 'unknown' } = useParams()
  const { push } = useToast()
  const pageBuilderQuery = usePageBuilderQuery(id)
  const revisionsQuery = usePageBuilderVersionsQuery(id)
  const saveMutation = useSavePageBuilderMutation(id)
  const publishMutation = usePublishPageBuilderMutation(id)
  const rollbackMutation = useRollbackPageBuilderMutation(id)
  const [previewHtml, setPreviewHtml] = useState<string | null>(null)

  const {
    blocks,
    selectedBlockId,
    dirty,
    validationIssues,
    setBlocks,
    selectBlock,
    updateBlock,
    deleteBlock,
    setDirty,
    setValidationIssues,
  } = useBuilderStore()

  useEffect(() => {
    if (!pageBuilderQuery.isSuccess) {
      return
    }

    setBlocks(normalizePageBlocks(pageBuilderQuery.data.blocks))
  }, [pageBuilderQuery.data, pageBuilderQuery.isSuccess, setBlocks])

  const saveBlocks = useCallback(async (nextBlocks: BuilderBlock[]) => {
    const normalized = normalizePageBlocks(nextBlocks)
    const validation = validatePageBlocks(normalized)
    setValidationIssues(validation.issues)

    if (!validation.isValid) {
      push({
        title: 'Ошибка валидации',
        description: 'Исправьте ошибки валидации перед сохранением.',
      })
      return
    }

    await saveMutation.mutateAsync(normalized)
    setDirty(false)
  }, [push, saveMutation, setDirty, setValidationIssues])

  const autosave = useCallback(async (_pageId: string, nextBlocks: BuilderBlock[]) => {
    await saveBlocks(nextBlocks)
    push({
      title: 'Autosave выполнен',
      description: `Черновик builder для ${id} сохранён.`,
    })
  }, [id, push, saveBlocks])

  useBuilderAutosave({
    pageId: id,
    blocks,
    onAutosave: autosave,
    enabled: pageBuilderQuery.isSuccess && dirty,
    intervalMs: 60_000,
  })

  const handleAddBlock = useCallback((type: BuilderBlock['type']) => {
    const nextBlocks = [...blocks, createBlock(type, blocks.length)]
    setBlocks(nextBlocks, true)
  }, [blocks, setBlocks])

  const handleDuplicate = useCallback((blockId: string) => {
    const index = blocks.findIndex((block) => block.id === blockId)
    if (index < 0) {
      return
    }
    const block = blocks[index]
    if (block === undefined) {
      return
    }
    const duplicated = duplicateBlock(block, index + 1)
    const nextBlocks = normalizePageBlocks([
      ...blocks.slice(0, index + 1),
      duplicated,
      ...blocks.slice(index + 1),
    ])
    setBlocks(nextBlocks, true)
    selectBlock(duplicated.id)
  }, [blocks, selectBlock, setBlocks])

  const handleDelete = useCallback((blockId: string) => {
    deleteBlock(blockId)
  }, [deleteBlock])

  const handleReorder = useCallback((sourceIndex: number, targetIndex: number) => {
    const next = reorderBlocks(blocks, sourceIndex, targetIndex)
    setBlocks(next, true)
  }, [blocks, setBlocks])

  if (pageBuilderQuery.isPending) {
    return <PageLoadingState />
  }

  if (pageBuilderQuery.isError || pageBuilderQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить builder"
        description="Проверьте доступ к странице и endpoint /admin/api/content/pages/{id}/builder."
      />
    )
  }

  return (
    <div>
      <UnsavedChangesGuard when={dirty} />
      <PageHeader
        title={`Page Builder: ${id}`}
        description="Structured Visual CMS Builder: каталог блоков, dnd, формы и preview."
      />

      <Card title="Builder actions">
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            className="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
            onClick={() => {
              void pageBuilderQuery.refetch()
            }}
          >
            Reload from storage
          </button>
          <button
            type="button"
            className="inline-flex h-9 items-center rounded-lg bg-emerald-600 px-3 text-sm font-medium text-white hover:bg-emerald-700"
            onClick={() => {
              void saveBlocks(blocks)
            }}
          >
            Save now
          </button>
        </div>
      </Card>

      <div className="mt-4">
        <Suspense fallback={<PageLoadingState />}>
          <PageBuilderContainer
            blocks={blocks}
            selectedBlockId={selectedBlockId}
            dirty={dirty}
            validationIssues={validationIssues}
            previewHtml={previewHtml}
            isSaving={saveMutation.isPending}
            onAddBlock={handleAddBlock}
            onSelectBlock={selectBlock}
            onReorderBlocks={handleReorder}
            onUpdateBlock={(block) => {
              updateBlock(block.id, () => block)
            }}
            onDeleteBlock={handleDelete}
            onDuplicateBlock={handleDuplicate}
            onSave={() => {
              void saveBlocks(blocks)
            }}
            onPreview={() => {
              void previewPageBuilder(id, blocks).then((response) => {
                setPreviewHtml(response.html)
              })
            }}
            onPublish={() => {
              void publishMutation.mutateAsync().then(() => {
                push({
                  title: 'Страница опубликована',
                  description: 'Builder-версия опубликована успешно.',
                })
              })
            }}
          />
        </Suspense>
      </div>

      <Card title="Revision hooks">
        <ul className="space-y-2 text-sm text-slate-600 dark:text-slate-300">
          {(revisionsQuery.data ?? []).map((revision) => (
            <li key={revision.id} className="flex items-center justify-between gap-2">
              <span>{revision.createdAt} — {revision.comment ?? `Revision #${revision.version}`}</span>
              <button
                type="button"
                className="rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700"
                onClick={() => {
                  void rollbackMutation.mutateAsync(revision.id).then(() => {
                    void pageBuilderQuery.refetch()
                    void revisionsQuery.refetch()
                    push({
                      title: 'Rollback выполнен',
                      description: `Версия ${revision.version} восстановлена.`,
                    })
                  })
                }}
              >
                Rollback
              </button>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  )
}
