import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type ReactNode } from 'react'
import { usePageRevisionsQuery, type PageStatus } from '../../entities/page/api'
import { adminQueryKeys } from '../../shared/api/query'
import { Button } from '../../shared/ui/button'
import { Dialog } from '../../shared/ui/dialog'
import { PagePublishingPanel } from '../publishing/PagePublishingPanel'
import { RevisionHistory } from '../publishing/RevisionHistory'
import { workflowQueryKey } from '../publishing/api'
import type { SaveState } from './save-state'

export interface PagePublishingSlotProps {
  pageId: string
  status: PageStatus
  saveState: SaveState
  hasUnsavedChanges: boolean
  saveAll: () => Promise<boolean>
}

/**
 * Кнопка в шапке редактора: открывает панель workflow (смена статуса, расписание, журнал).
 * Несохранённые правки сохраняются перед открытием, чтобы расписание и «неопубликованные изменения» считались по актуальному состоянию.
 */
export function PagePublishingSlot({ pageId, status, saveState, hasUnsavedChanges, saveAll }: PagePublishingSlotProps): ReactNode {
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const [opening, setOpening] = useState(false)

  useEffect(() => {
    void queryClient.invalidateQueries({ queryKey: workflowQueryKey(pageId) })
  }, [pageId, queryClient, status])

  const openPanel = async (): Promise<void> => {
    setOpening(true)
    try {
      if (hasUnsavedChanges && !(await saveAll())) {
        return
      }
      await queryClient.invalidateQueries({ queryKey: workflowQueryKey(pageId) })
      setOpen(true)
    } finally {
      setOpening(false)
    }
  }

  const onChanged = async (): Promise<void> => {
    await queryClient.invalidateQueries({ queryKey: adminQueryKeys.pages })
  }

  return (
    <>
      <Button type="button" variant="outline" disabled={opening || saveState === 'saving'} onClick={() => void openPanel()}>
        Публикация и расписание
      </Button>
      <Dialog
        open={open}
        onOpenChange={setOpen}
        title="Публикация страницы"
        description="Статус, расписание публикации и снятия, журнал событий."
        contentClassName="max-w-2xl max-h-[85vh] overflow-y-auto"
      >
        <PagePublishingPanel pageId={pageId} onChanged={onChanged} />
        <div className="mt-4 flex justify-end">
          <Button type="button" variant="outline" onClick={() => setOpen(false)}>Закрыть</Button>
        </div>
      </Dialog>
    </>
  )
}

export interface PageRevisionsSlotProps {
  pageId: string
  status: PageStatus
}

/** Сравнение сохранённых ревизий с текущей рабочей версией. Откат остаётся в списке «История версий». */
export function PageRevisionsSlot({ pageId }: PageRevisionsSlotProps): ReactNode {
  const revisionsQuery = usePageRevisionsQuery(pageId)

  if (!revisionsQuery.isSuccess || revisionsQuery.data.length === 0) {
    return null
  }

  return <RevisionHistory pageId={pageId} revisions={revisionsQuery.data} allowRollback={false} onRolledBack={() => undefined} />
}
