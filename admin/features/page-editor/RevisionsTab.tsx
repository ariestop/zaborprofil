import { useQueryClient } from '@tanstack/react-query'
import { usePageRevisionsQuery } from '../../entities/page/api'
import { adminQueryKeys } from '../../shared/api/query'
import { EmptyState, ErrorState, PageLoadingState } from '../../shared/ui'
import { RevisionHistory } from '../publishing/RevisionHistory'
import type { PageEditorController } from './usePageEditorController'

interface RevisionsTabProps {
  controller: PageEditorController
}

/** Вкладка «История версий»: один список версий со сравнением и откатом. После отката редактор перечитывает страницу. */
export function RevisionsTab({ controller }: RevisionsTabProps) {
  const { pageId } = controller
  const queryClient = useQueryClient()
  const revisionsQuery = usePageRevisionsQuery(pageId)

  const onRolledBack = async (): Promise<void> => {
    await controller.reloadFromServer()
    // Откат создаёт новую ревизию и меняет страницу: список версий и данные страницы перечитываются.
    await queryClient.invalidateQueries({ queryKey: adminQueryKeys.pageById(pageId) })
  }

  if (revisionsQuery.isPending) {
    return <PageLoadingState />
  }

  if (revisionsQuery.isError) {
    return <ErrorState title="Не удалось загрузить историю" description="Обновите страницу или повторите попытку позже." />
  }

  if (revisionsQuery.data.length === 0) {
    return <EmptyState title="Версий пока нет" description="Они появятся после первой публикации страницы." />
  }

  return (
    <RevisionHistory
      pageId={pageId}
      revisions={revisionsQuery.data}
      hasUnsavedChanges={controller.hasUnsavedChanges}
      onRolledBack={onRolledBack}
    />
  )
}
