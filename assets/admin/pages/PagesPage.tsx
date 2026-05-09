import { Link } from 'react-router-dom'
import { createColumnHelper } from '@tanstack/react-table'
import { PageHeader, Card, DataTable, ErrorState, PageLoadingState } from '../shared/ui'
import { usePagesQuery } from '../entities/page/api'
import type { ContentPageItem } from '../types/api'

export default function PagesPage() {
  const pagesQuery = usePagesQuery()
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

  return (
    <div>
      <PageHeader
        title="Pages"
        description="Управление страницами через TanStack Table и backend API."
      />
      <Card>
        <DataTable columns={columns} data={pagesQuery.data ?? []} />
      </Card>
    </div>
  )
}
