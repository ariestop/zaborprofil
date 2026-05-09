import { lazy, Suspense, useCallback, useEffect, useMemo } from 'react'
import { useParams } from 'react-router-dom'
import { PageHeader, Card, ErrorState, PageLoadingState } from '../shared/ui'
import type { BuilderSnapshot, BuilderStorageAdapter } from '../modules/page-builder/types'
import { useBuilderDraft } from '../modules/page-builder/hooks/useBuilderDraft'
import { useBuilderAutosave } from '../modules/page-builder/hooks/useBuilderAutosave'
import {
  findBuilderCanvasBlock,
  reorderPageBlocks,
  updateBlockRichText,
  upsertBuilderCanvasBlock,
  usePageDetailQuery,
  usePagePreviewLinkQuery,
  usePageRevisionsQuery,
} from '../entities/page/api'
import { useToast } from '../app/providers/toast-provider'

const PageBuilderContainer = lazy(async () => import('../modules/page-builder/components/PageBuilderContainer').then((module) => ({ default: module.PageBuilderContainer })))

export default function PageBuilderPage() {
  const { id = 'unknown' } = useParams()
  const { push } = useToast()
  const pageQuery = usePageDetailQuery(id)
  const revisionsQuery = usePageRevisionsQuery(id)
  const previewQuery = usePagePreviewLinkQuery(id)
  const { snapshot, setSnapshot } = useBuilderDraft({ html: '<section><h2>Builder canvas</h2></section>', css: '' })

  const builderCanvasBlock = useMemo(
    () => (pageQuery.data !== undefined ? findBuilderCanvasBlock(pageQuery.data.blocks) : undefined),
    [pageQuery.data],
  )

  useEffect(() => {
    if (!pageQuery.isSuccess) {
      return
    }

    setSnapshot({
      html: typeof builderCanvasBlock?.content.html === 'string'
        ? builderCanvasBlock.content.html
        : '<section><h2>Builder canvas</h2></section>',
      css: typeof builderCanvasBlock?.content.css === 'string'
        ? builderCanvasBlock.content.css
        : '',
    })
  }, [builderCanvasBlock, pageQuery.isSuccess, setSnapshot])

  const storageAdapter = useMemo<BuilderStorageAdapter>(() => ({
    load: async () => {
      const page = await pageQuery.refetch()
      const detail = page.data
      if (detail === undefined) {
        return { html: '<section><h2>Builder canvas</h2></section>', css: '' }
      }

      const builderBlock = findBuilderCanvasBlock(detail.blocks)
      return {
        html: typeof builderBlock?.content.html === 'string' ? builderBlock.content.html : '<section><h2>Builder canvas</h2></section>',
        css: typeof builderBlock?.content.css === 'string' ? builderBlock.content.css : '',
      }
    },
    save: async (pageId: string, nextSnapshot: BuilderSnapshot) => {
      await upsertBuilderCanvasBlock({
        pageId,
        snapshot: nextSnapshot,
        existingBlock: builderCanvasBlock,
      })
    },
  }), [builderCanvasBlock, pageQuery])

  const autosave = useCallback(async (pageId: string, nextSnapshot: BuilderSnapshot) => {
    await storageAdapter.save(pageId, nextSnapshot)
    push({
      title: 'Autosave выполнен',
      description: `Черновик builder для ${pageId} сохранён.`,
    })
  }, [push, storageAdapter])

  useBuilderAutosave({
    pageId: id,
    snapshot,
    onAutosave: autosave,
    enabled: pageQuery.isSuccess,
  })

  const loadSnapshot = useCallback(async () => {
    const loaded = await storageAdapter.load(id)
    setSnapshot(loaded)
  }, [id, setSnapshot, storageAdapter])

  const saveRichText = useCallback(async (blockId: string, richText: string) => {
    const detail = pageQuery.data
    if (detail === undefined) {
      return
    }

    const block = detail.blocks.find((item) => item.id === blockId)
    if (block === undefined) {
      return
    }

    await updateBlockRichText(block, richText)
    push({
      title: 'Rich text обновлён',
      description: 'Изменения сохранены в настройках блока.',
    })
    await pageQuery.refetch()
  }, [pageQuery, push])

  const reorderBlocks = useCallback(async (blockIds: string[]) => {
    await reorderPageBlocks(id, blockIds)
    await pageQuery.refetch()
  }, [id, pageQuery])

  if (pageQuery.isPending) {
    return <PageLoadingState />
  }

  if (pageQuery.isError || pageQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить builder"
        description="Проверьте доступ к странице и endpoint /admin/api/content/pages/{id}."
      />
    )
  }

  return (
    <div>
      <PageHeader
        title={`Page Builder: ${id}`}
        description="Snapshot HTML/CSS + autosave + preview + dnd reorder."
      />

      <Card title="Builder storage adapter">
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            className="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
            onClick={() => {
              void loadSnapshot()
            }}
          >
            Reload from storage
          </button>
          <button
            type="button"
            className="inline-flex h-9 items-center rounded-lg bg-emerald-600 px-3 text-sm font-medium text-white hover:bg-emerald-700"
            onClick={() => {
              void autosave(id, snapshot)
            }}
          >
            Save now
          </button>
        </div>
      </Card>

      <div className="mt-4">
        <Suspense fallback={<PageLoadingState />}>
          <PageBuilderContainer
            pageId={id}
            blocks={pageQuery.data.blocks}
            snapshot={snapshot}
            previewUrl={previewQuery.data?.previewUrl ?? null}
            onSnapshotChange={setSnapshot}
            onReorderBlocks={reorderBlocks}
            onSaveRichText={saveRichText}
            versions={(revisionsQuery.data ?? []).map((revision) => ({
              id: revision.id,
              createdAt: revision.createdAt,
              author: 'system',
              comment: revision.comment ?? `Revision #${revision.version}`,
            }))}
            blockRegistry={[
              { type: 'hero', title: 'Hero', category: 'Маркетинг' },
              { type: 'features', title: 'Feature Grid', category: 'Контент' },
              { type: 'cta', title: 'CTA', category: 'Конверсия' },
              { type: 'builder_canvas', title: 'Builder Canvas', category: 'Layout' },
            ]}
          />
        </Suspense>
      </div>
    </div>
  )
}
