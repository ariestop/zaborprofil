import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useToast } from '../../app/providers/toast-provider'
import { usePageRevisionsQuery, useRollbackPageBuilderMutation, type PageStatus } from '../../entities/page/api'
import { Button, Card, ConfirmDialog, EmptyState, ErrorState, PageLoadingState } from '../../shared/ui'
import type { PageRevisionItem } from '../../types/api'
import { describeApiError } from '../seo/redirects/redirect-rules'
import { workflowQueryKey } from '../publishing/api'
import { PageRevisionsSlot } from './slots'
import type { PageEditorController } from './usePageEditorController'

interface RevisionsTabProps {
  controller: PageEditorController
  status: PageStatus
}

function formatRevisionDate(value: string): string {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('ru-RU')
}

export function RevisionsTab({ controller, status }: RevisionsTabProps) {
  const { pageId } = controller
  const { push } = useToast()
  const queryClient = useQueryClient()
  const revisionsQuery = usePageRevisionsQuery(pageId)
  const rollbackMutation = useRollbackPageBuilderMutation(pageId)
  const [target, setTarget] = useState<PageRevisionItem | null>(null)

  const rollback = async (revision: PageRevisionItem): Promise<void> => {
    setTarget(null)
    try {
      await rollbackMutation.mutateAsync(revision.id)
      await controller.reloadFromServer()
      await queryClient.invalidateQueries({ queryKey: workflowQueryKey(pageId) })
      push({ title: 'Версия восстановлена', description: `Версия ${revision.version} загружена в редактор.` })
    } catch (error) {
      push({ title: 'Не удалось восстановить версию', description: describeApiError(error, 'Проверьте права и повторите попытку.') })
    }
  }

  return (
    <div className="grid gap-4">
      <Card title="История версий" description="Версия создаётся при публикации. Восстановление заменяет содержимое страницы и закрывает несохранённые правки.">
        {revisionsQuery.isPending ? <PageLoadingState /> : null}
        {revisionsQuery.isError ? <ErrorState title="Не удалось загрузить историю" description="Проверьте endpoint /admin/api/content/pages/{id}/revisions." /> : null}
        {revisionsQuery.isSuccess && revisionsQuery.data.length === 0 ? (
          <EmptyState title="Версий пока нет" description="Они появятся после первой публикации страницы." />
        ) : null}
        {revisionsQuery.isSuccess && revisionsQuery.data.length > 0 ? (
          <ul className="divide-y divide-surface-strong dark:divide-slate-800" data-testid="revisions-list">
            {revisionsQuery.data.map((revision) => (
              <li key={revision.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div className="min-w-0">
                  <p className="text-sm font-medium">Версия {revision.version} · {formatRevisionDate(revision.createdAt)}</p>
                  <p className="truncate text-xs text-graphite dark:text-slate-400">
                    {revision.title} · {revision.path}{revision.comment !== null && revision.comment !== '' ? ` · ${revision.comment}` : ''}
                  </p>
                </div>
                <Button type="button" size="sm" variant="outline" disabled={rollbackMutation.isPending} onClick={() => setTarget(revision)}>
                  Восстановить
                </Button>
              </li>
            ))}
          </ul>
        ) : null}
      </Card>

      <PageRevisionsSlot pageId={pageId} status={status} />

      <ConfirmDialog
        open={target !== null}
        title={target === null ? '' : `Восстановить версию ${target.version}?`}
        description={controller.hasUnsavedChanges ? 'Несохранённые изменения в редакторе будут потеряны.' : 'Текущее содержимое страницы будет заменено выбранной версией.'}
        confirmLabel="Восстановить"
        onCancel={() => setTarget(null)}
        onConfirm={() => {
          if (target !== null) {
            void rollback(target)
          }
        }}
      />
    </div>
  )
}
