import { Link } from 'react-router-dom'
import { PageCreateForm } from '../features/page-editor/PageCreateForm'
import { PageHeader } from '../shared/ui'

export default function PageCreatePage() {
  return (
    <div>
      <PageHeader
        title="Новая страница"
        description="Заполните основные поля — остальное можно настроить в редакторе."
        actions={<Link to="/admin/pages" className="text-sm text-emerald-700 underline dark:text-emerald-400">К списку страниц</Link>}
      />
      <PageCreateForm />
    </div>
  )
}
