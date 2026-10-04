import { Navigate, useParams } from 'react-router-dom'
import { usePageEditorDataQuery } from '../entities/page/api'
import { PageEditor } from '../features/page-editor/PageEditor'
import { resolveEditorTab } from '../features/page-editor/editor-tab'
import { ErrorState, PageLoadingState } from '../shared/ui'

export default function PageEditorPage() {
  const { id = '', tab } = useParams()
  const editorQuery = usePageEditorDataQuery(id)
  const resolvedTab = resolveEditorTab(tab)

  if (resolvedTab === null) {
    return <Navigate to={`/admin/pages/${id}`} replace />
  }

  if (editorQuery.isPending) {
    return <PageLoadingState />
  }

  if (editorQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить страницу"
        description="Страница не найдена или нет доступа. Вернитесь к списку страниц и откройте её заново."
      />
    )
  }

  return (
    <PageEditor
      key={id}
      page={editorQuery.data.page}
      initialBlocks={editorQuery.data.builder.blocks}
      initialBuilderVersion={editorQuery.data.builder.version}
      tab={resolvedTab}
    />
  )
}
