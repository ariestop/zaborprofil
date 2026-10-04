import { useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useToast } from '../app/providers/toast-provider'
import { exportLeadsCsv, useLeadAssigneesQuery, useLeadsQuery } from '../entities/lead/api'
import {
  LEAD_STATUS_LABELS,
  leadSourceLabel,
  leadStatusLabel,
  type LeadFilters,
  type LeadListParams,
  type LeadSortField,
} from '../entities/lead/model'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { useDebouncedValue } from '../shared/hooks/use-debounced-value'
import { downloadTextFile } from '../shared/lib/download'
import { formatDateTime, formatNumber } from '../shared/lib/format'
import { Badge, Button, Card, EmptyState, ErrorState, Input, PageHeader, Select, Table } from '../shared/ui'
import { Pagination } from '../shared/ui/pagination'
import type { LeadStatus } from '../types/api'

const PER_PAGE = 25
const STATUSES: LeadStatus[] = ['new', 'in_progress', 'done', 'spam']
const SORTS: LeadSortField[] = ['createdAt', 'updatedAt', 'name', 'status', 'source']

const sortOptions: Array<{ value: LeadSortField, label: string }> = [
  { value: 'createdAt', label: 'По дате создания' },
  { value: 'updatedAt', label: 'По дате изменения' },
  { value: 'name', label: 'По имени' },
  { value: 'status', label: 'По статусу' },
  { value: 'source', label: 'По источнику' },
]

export function statusTone(status: LeadStatus): 'neutral' | 'success' | 'warning' {
  if (status === 'done') {
    return 'success'
  }

  return status === 'new' || status === 'spam' ? 'warning' : 'neutral'
}

function readParams(search: URLSearchParams): LeadListParams {
  const status = search.get('status')
  const sort = search.get('sort')

  return {
    q: '',
    status: STATUSES.includes(status as LeadStatus) ? (status as LeadStatus) : 'all',
    source: search.get('source') ?? '',
    from: search.get('from') ?? '',
    to: search.get('to') ?? '',
    assignee: search.get('assignee') ?? 'all',
    sort: SORTS.includes(sort as LeadSortField) ? (sort as LeadSortField) : 'createdAt',
    direction: search.get('direction') === 'asc' ? 'asc' : 'desc',
    page: Math.max(1, Number.parseInt(search.get('page') ?? '1', 10) || 1),
    perPage: PER_PAGE,
  }
}

function writeParams(params: LeadListParams): URLSearchParams {
  const search = new URLSearchParams()
  const entries: Array<[string, string, string]> = [
    ['status', params.status, 'all'],
    ['source', params.source, ''],
    ['from', params.from, ''],
    ['to', params.to, ''],
    ['assignee', params.assignee, 'all'],
    ['sort', params.sort, 'createdAt'],
    ['direction', params.direction, 'desc'],
    ['page', String(params.page), '1'],
  ]
  for (const [key, value, fallback] of entries) {
    if (value !== fallback) {
      search.set(key, value)
    }
  }

  return search
}

export default function CrmPage() {
  const { push } = useToast()
  const [searchParams, setSearchParams] = useSearchParams()
  const urlParams = useMemo(() => readParams(searchParams), [searchParams])
  const [searchText, setSearchText] = useState('')
  const debouncedSearch = useDebouncedValue(searchText)
  const params: LeadListParams = { ...urlParams, q: debouncedSearch.trim() }

  const leadsQuery = useLeadsQuery(params)
  const assigneesQuery = useLeadAssigneesQuery()
  const [exporting, setExporting] = useState(false)

  const update = (patch: Partial<LeadListParams>) => {
    setSearchParams(writeParams({ ...params, page: 1, ...patch }), { replace: true })
  }

  const exportCsv = async () => {
    const filters: LeadFilters = {
      q: params.q,
      status: params.status,
      source: params.source,
      from: params.from,
      to: params.to,
      assignee: params.assignee,
    }
    setExporting(true)
    try {
      const csv = await exportLeadsCsv(filters, params.sort, params.direction)
      downloadTextFile(`leads-${new Date().toISOString().slice(0, 10)}.csv`, csv)
    } catch (error) {
      push({ title: 'Не удалось выгрузить CSV', description: describeApiError(error, 'Недостаточно прав или сервер недоступен.') })
    } finally {
      setExporting(false)
    }
  }

  const data = leadsQuery.data
  const sources = data?.sources ?? []
  const assignees = assigneesQuery.data?.items ?? []
  const hasFilters = params.q !== '' || params.status !== 'all' || params.source !== ''
    || params.from !== '' || params.to !== '' || params.assignee !== 'all'

  return (
    <div className="grid gap-4">
      <PageHeader
        title="CRM"
        description="Заявки с сайта: поиск, фильтры, ответственные и история работы с клиентом."
        actions={(
          <Button type="button" variant="outline" disabled={exporting} onClick={() => void exportCsv()}>
            Экспорт CSV
          </Button>
        )}
      />

      <Card>
        <div className="grid gap-3">
          <div className="flex flex-wrap gap-2" role="group" aria-label="Фильтр по статусу">
            <Button
              type="button"
              size="sm"
              variant={params.status === 'all' ? 'default' : 'outline'}
              aria-pressed={params.status === 'all'}
              onClick={() => update({ status: 'all' })}
            >
              Все{data === undefined ? '' : ` · ${formatNumber(data.counts.total)}`}
            </Button>
            {STATUSES.map((status) => (
              <Button
                key={status}
                type="button"
                size="sm"
                variant={params.status === status ? 'default' : 'outline'}
                aria-pressed={params.status === status}
                onClick={() => update({ status })}
              >
                {LEAD_STATUS_LABELS[status]}{data === undefined ? '' : ` · ${formatNumber(data.counts.byStatus[status] ?? 0)}`}
              </Button>
            ))}
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <div className="w-full max-w-sm">
              <Input
                type="search"
                aria-label="Поиск заявок"
                placeholder="Имя, телефон, email или текст заявки"
                value={searchText}
                onChange={(event) => {
                  setSearchText(event.target.value)
                  if (urlParams.page !== 1) {
                    update({})
                  }
                }}
              />
            </div>
            <Select
              value={params.source === '' ? 'all' : params.source}
              onValueChange={(value) => update({ source: value === 'all' ? '' : value })}
              options={[
                { value: 'all', label: 'Все источники' },
                ...sources.map((source) => ({ value: source, label: leadSourceLabel(source) })),
              ]}
            />
            <Select
              value={params.assignee}
              onValueChange={(value) => update({ assignee: value })}
              options={[
                { value: 'all', label: 'Любой ответственный' },
                { value: 'me', label: 'Мои заявки' },
                { value: 'none', label: 'Без ответственного' },
                ...assignees.map((assignee) => ({ value: assignee.id, label: assignee.email })),
              ]}
            />
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
              С
              <Input type="date" aria-label="Дата от" className="w-44" value={params.from} onChange={(event) => update({ from: event.target.value })} />
            </label>
            <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
              По
              <Input type="date" aria-label="Дата до" className="w-44" value={params.to} onChange={(event) => update({ to: event.target.value })} />
            </label>
            <Select value={params.sort} onValueChange={(value) => update({ sort: value as LeadSortField })} options={sortOptions} />
            <Button
              type="button"
              variant="outline"
              size="sm"
              aria-label="Направление сортировки"
              onClick={() => update({ direction: params.direction === 'desc' ? 'asc' : 'desc' })}
            >
              {params.direction === 'desc' ? 'По убыванию ↓' : 'По возрастанию ↑'}
            </Button>
            {hasFilters ? (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => {
                  setSearchText('')
                  setSearchParams(new URLSearchParams(), { replace: true })
                }}
              >
                Сбросить фильтры
              </Button>
            ) : null}
          </div>

          {leadsQuery.isError ? (
            <ErrorState
              title="Не удалось загрузить заявки"
              description="Проверьте endpoint /admin/api/leads и право leads.view."
            />
          ) : null}

          {leadsQuery.isPending ? <p className="text-sm text-slate-500">Загрузка...</p> : null}

          {data !== undefined && data.items.length === 0 ? (
            <EmptyState
              title={hasFilters ? 'Ничего не найдено' : 'Заявок пока нет'}
              description={hasFilters ? 'Измените поисковый запрос или сбросьте фильтры.' : 'Новые заявки с сайта появятся здесь.'}
            />
          ) : null}

          {data !== undefined && data.items.length > 0 ? (
            <>
              <Table
                head={(
                  <tr>
                    {['Создана', 'Клиент', 'Контакты', 'Источник', 'Статус', 'Ответственный'].map((title) => (
                      <th key={title} className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</th>
                    ))}
                  </tr>
                )}
                body={data.items.map((lead) => (
                  <tr key={lead.id} data-testid="lead-row">
                    <td className="whitespace-nowrap px-3 py-2 text-sm">{formatDateTime(lead.createdAt)}</td>
                    <td className="px-3 py-2 text-sm">
                      <Link className="font-medium text-emerald-700 hover:underline dark:text-emerald-400" to={`/admin/crm/${lead.id}`}>
                        {lead.name}
                      </Link>
                      {lead.messagePreview !== null ? (
                        <p className="mt-0.5 max-w-xs truncate text-xs text-slate-500 dark:text-slate-400">{lead.messagePreview}</p>
                      ) : null}
                    </td>
                    <td className="px-3 py-2 text-sm">
                      <div>{lead.phone}</div>
                      {lead.email !== null ? <div className="text-xs text-slate-500 dark:text-slate-400">{lead.email}</div> : null}
                    </td>
                    <td className="px-3 py-2 text-sm">{leadSourceLabel(lead.source)}</td>
                    <td className="px-3 py-2 text-sm"><Badge tone={statusTone(lead.status)}>{leadStatusLabel(lead.status)}</Badge></td>
                    <td className="px-3 py-2 text-sm">{lead.assignee === null ? '—' : (lead.assignee.email ?? 'Пользователь удалён')}</td>
                  </tr>
                ))}
              />
              <Pagination page={data.page} pages={data.pages} total={data.total} onPageChange={(page) => update({ page })} />
            </>
          ) : null}
        </div>
      </Card>
    </div>
  )
}
