import { Link, useNavigate } from 'react-router-dom'
import { createColumnHelper } from '@tanstack/react-table'
import { PageHeader, Card, DataTable, ErrorState, PageLoadingState, Button } from '../shared/ui'
import { useCreatePageMutation, usePagesQuery } from '../entities/page/api'
import type { ContentPageItem } from '../types/api'
import { useToast } from '../app/providers/toast-provider'

export default function PagesPage() {
  const navigate = useNavigate()
  const { push } = useToast()
  const pagesQuery = usePagesQuery()
  const createPageMutation = useCreatePageMutation()
  const columnHelper = createColumnHelper<ContentPageItem>()
  const columns = [
    columnHelper.accessor('slug', { header: 'Slug' }),
    columnHelper.accessor('title', { header: 'Название' }),
    columnHelper.accessor('status', { header: 'Статус' }),
    columnHelper.display({
      id: 'actions',
      header: 'Действие',
      cell: ({ row }) => (
        <Link
          to={`/admin/pages/${row.original.id}`}
          className="inline-flex h-8 items-center rounded-lg border border-slate-300 px-3 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800"
        >
          Открыть
        </Link>
      ),
    }),
  ]

  if (pagesQuery.isPending) {
    return <PageLoadingState />
  }

  if (pagesQuery.isError) {
    return (
      <ErrorState
        title="Не удалось загрузить список страниц"
        description="Проверьте endpoint /admin/api/content/pages и права pages.view."
      />
    )
  }

  const createPage = async (): Promise<void> => {
    const suffix = Date.now().toString(36)
    const slug = `new-page-${suffix}`

    try {
      const createdPage = await createPageMutation.mutateAsync({
        type: 'landing',
        title: `Новая страница ${suffix}`,
        slug,
        path: `/${slug}/`,
        h1: `Новая страница ${suffix}`,
        template: 'default',
        sortOrder: 0,
        isIndexable: true,
        parentId: null,
        visibility: 'public',
      })

      push({
        title: 'Страница создана',
        description: 'Открываю редактирование новой страницы.',
      })

      navigate(`/admin/pages/${createdPage.id}`)
    } catch (_error) {
      push({
        title: 'Не удалось создать страницу',
        description: 'Проверьте доступ pages.create и попробуйте снова.',
      })
    }
  }

  return (
    <div>
      <PageHeader
        title="Pages"
        description="Управление страницами через TanStack Table и backend API."
        actions={(
          <Button type="button" onClick={createPage} disabled={createPageMutation.isPending}>
            {createPageMutation.isPending ? 'Создание...' : 'Создать страницу'}
          </Button>
        )}
      />
      <Card>
        <DataTable columns={columns} data={pagesQuery.data ?? []} />
      </Card>
    </div>
  )
}
