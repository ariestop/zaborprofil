import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useToast } from '../../app/providers/toast-provider'
import type { PageRevisionItem } from '../../types/api'
import { Button } from '../../shared/ui/button'
import { ConfirmDialog } from '../../shared/ui/confirm-dialog'
import { Dialog } from '../../shared/ui/dialog'
import { fetchRevisionDiff, rollbackRevision, workflowQueryKey } from './api'
import { formatDateTime } from './datetime'
import { RevisionDiffView } from './RevisionDiffView'

interface RevisionHistoryProps {
  pageId: string
  revisions: PageRevisionItem[]
  /** `false` — только сравнение, без отката (откат доступен в другом месте экрана). */
  allowRollback?: boolean
  onRolledBack: () => Promise<void> | void
}

export function RevisionHistory({ pageId, revisions, allowRollback = true, onRolledBack }: RevisionHistoryProps) {
  const queryClient = useQueryClient()
  const { push } = useToast()
  const [diffRevision, setDiffRevision] = useState<PageRevisionItem | null>(null)
  const [rollbackTarget, setRollbackTarget] = useState<PageRevisionItem | null>(null)
  const [error, setError] = useState<string | null>(null)

  const diffQuery = useQuery({
    queryKey: ['page-revision-diff', pageId, diffRevision?.id],
    queryFn: () => fetchRevisionDiff(pageId, diffRevision?.id ?? '', 'current'),
    enabled: diffRevision !== null,
  })

  const rollbackMutation = useMutation({
    mutationFn: (revision: PageRevisionItem) => rollbackRevision(pageId, revision.id),
    onSuccess: async (_page, revision) => {
      setError(null)
      setRollbackTarget(null)
      push({ title: `Откат к версии v${revision.version}` })
      await queryClient.invalidateQueries({ queryKey: workflowQueryKey(pageId) })
      await onRolledBack()
    },
    onError: (caught) => {
      setRollbackTarget(null)
      setError(caught instanceof Error ? caught.message : 'Не удалось выполнить откат')
    },
  })

  return (
    <section className="rounded-2xl border border-line bg-white p-6 shadow-xs" aria-label="История ревизий">
      <h3 className="text-base font-semibold text-ink">{allowRollback ? 'История публикаций' : 'Сравнение с текущей версией'}</h3>
      {revisions.length === 0 && <p className="mt-2 text-sm text-graphite">Публикаций пока нет.</p>}
      {error && <p role="alert" className="mt-2 text-sm text-red-700">{error}</p>}
      {revisions.map((revision) => (
        <div key={revision.id} className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line px-4 py-3">
          <div>
            <p className="font-medium text-ink">v{revision.version} · {revision.title}</p>
            <p className="text-xs text-graphite">{revision.path} · {formatDateTime(revision.createdAt)} · {revision.comment ?? 'без комментария'}</p>
          </div>
          <div className="flex gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => setDiffRevision(revision)}>Сравнить с текущей</Button>
            {allowRollback && (
              <Button type="button" variant="outline" size="sm" disabled={rollbackMutation.isPending} onClick={() => setRollbackTarget(revision)}>Откатить</Button>
            )}
          </div>
        </div>
      ))}

      <Dialog
        open={diffRevision !== null}
        onOpenChange={(open) => { if (!open) setDiffRevision(null) }}
        title={diffRevision ? `Изменения с версии v${diffRevision.version}` : 'Изменения'}
        description="Что изменилось в рабочей версии страницы по сравнению с выбранной ревизией."
        contentClassName="max-w-3xl max-h-[85vh] overflow-y-auto"
      >
        <div className="mt-4">
          {diffQuery.isPending && <p className="text-sm text-graphite">Сравнение...</p>}
          {diffQuery.isError && <p role="alert" className="text-sm text-red-700">Не удалось сравнить ревизии.</p>}
          {diffQuery.data && <RevisionDiffView diff={diffQuery.data} />}
          <div className="mt-4 flex justify-end">
            <Button type="button" variant="outline" onClick={() => setDiffRevision(null)}>Закрыть</Button>
          </div>
        </div>
      </Dialog>

      <ConfirmDialog
        open={rollbackTarget !== null}
        title={rollbackTarget ? `Откатить к версии v${rollbackTarget.version}?` : 'Откат'}
        description="Рабочая версия страницы будет заменена содержимым ревизии. Это действие записывается в журнал и создаёт новую ревизию."
        confirmLabel="Откатить"
        onCancel={() => setRollbackTarget(null)}
        onConfirm={() => { if (rollbackTarget) rollbackMutation.mutate(rollbackTarget) }}
      />
    </section>
  )
}
