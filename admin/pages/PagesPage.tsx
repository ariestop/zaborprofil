import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { usePagesQuery } from '../entities/page/api'
import { pageStatusLabels, pageTypeLabels, statusTone } from '../features/page-editor/page-status'
import { preloadPageEditorOnIntent } from '../routes/prefetch'
import { useCan } from '../stores/auth'
import { Badge, EmptyState, ErrorState, Input, PageHeader, PageLoadingState, Table } from '../shared/ui'
import { NativeSelect } from '../features/page-editor/fields'

const ALL_STATUSES = 'all'

export default function PagesPage() {
  const pagesQuery = usePagesQuery()
  const canCreate = useCan('pages.create')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<string>(ALL_STATUSES)

  const pages = useMemo(() => {
    const normalized = search.trim().toLowerCase()
    return (pagesQuery.data ?? [])
      .filter((page) => (status === ALL_STATUSES ? page.status !== 'deleted' : page.status === status))
      .filter((page) => normalized === '' || `${page.title} ${page.path}`.toLowerCase().includes(normalized))
      .sort((left, right) => left.path.localeCompare(right.path))
  }, [pagesQuery.data, search, status])

  return (
    <div>
      <PageHeader
        title="Страницы"
        description="Контент, SEO и публикация страниц сайта. Каждая страница редактируется на одном экране."
        actions={canCreate ? (
          <Link
            to="/admin/pages/new"
            className="inline-flex h-10 items-center rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white hover:bg-emerald-700"
          >
            Создать страницу
          </Link>
        ) : undefined}
      />

      <div className="mb-4 flex flex-wrap items-end gap-3">
        <label className="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">
          Поиск
          <Input value={search} placeholder="Название или адрес" onChange={(event) => setSearch(event.target.value)} />
        </label>
        <label className="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">
          Статус
          <NativeSelect value={status} onChange={(event) => setStatus(event.target.value)}>
            <option value={ALL_STATUSES}>Все, кроме удалённых</option>
            {Object.entries(pageStatusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </NativeSelect>
        </label>
      </div>

      {pagesQuery.isPending ? <PageLoadingState /> : null}
      {pagesQuery.isError ? <ErrorState title="Не удалось загрузить страницы" description="Проверьте endpoint /admin/api/content/pages и доступы." /> : null}
      {pagesQuery.isSuccess && pages.length === 0 ? (
        <EmptyState title="Страниц не найдено" description="Измените фильтры или создайте первую страницу." />
      ) : null}
      {pagesQuery.isSuccess && pages.length > 0 ? (
        <Table
          head={(
            <tr>
              {['Страница', 'Адрес', 'Тип', 'Статус', ''].map((title) => (
                <th key={title} className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</th>
              ))}
            </tr>
          )}
          body={pages.map((page) => (
            <tr key={page.id} data-testid="page-row">
              <td className="px-3 py-2 text-sm font-medium">
                <Link to={`/admin/pages/${page.id}`} onMouseEnter={preloadPageEditorOnIntent} onFocus={preloadPageEditorOnIntent} className="text-emerald-800 hover:underline dark:text-emerald-300">{page.title}</Link>
              </td>
              <td className="px-3 py-2 font-mono text-sm break-all">{page.path}</td>
              <td className="px-3 py-2 text-sm">{pageTypeLabels[page.type] ?? page.type}</td>
              <td className="px-3 py-2 text-sm"><Badge tone={statusTone(page.status)}>{pageStatusLabels[page.status]}</Badge></td>
              <td className="px-3 py-2 text-right text-sm">
                <Link to={`/admin/pages/${page.id}`} className="underline">Редактировать</Link>
                {page.status === 'published' ? (
                  <a href={page.path} target="_blank" rel="noreferrer" className="ml-3 underline">Открыть на сайте</a>
                ) : null}
              </td>
            </tr>
          ))}
        />
      ) : null}
    </div>
  )
}
