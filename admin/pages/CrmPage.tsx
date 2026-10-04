import { useMemo, useState } from 'react'
import { Link, useMatch, useNavigate, useSearchParams } from 'react-router-dom'
import { useHotkeys } from 'react-hotkeys-hook'
import { useToast } from '../app/providers/toast-provider'
import { exportLeadsCsv, useLeadAssigneesQuery, useLeadsQuery } from '../entities/lead/api'
import {
  LEAD_STATUS_LABELS,
  leadSourceLabel,
  leadStatusLabel,
  leadStatusTone,
  type LeadFilters,
  type LeadListParams,
  type LeadSortField,
} from '../entities/lead/model'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { useDebouncedValue } from '../shared/hooks/use-debounced-value'
import { downloadTextFile } from '../shared/lib/download'
import { cn } from '../shared/lib/cn'
import { formatDateTime, formatNumber } from '../shared/lib/format'
import { Badge, Button, EmptyState, ErrorState, Input, PageHeader, Select } from '../shared/ui'
import { Pagination } from '../shared/ui/pagination'
import type { LeadStatus } from '../types/api'
import LeadDetailPage from './LeadDetailPage'

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

function withSearch(path: string, search: URLSearchParams): string {
  const query = search.toString()

  return query === '' ? path : `${path}?${query}`
}

/**
 * Рабочее место по заявкам: слева список с фильтрами, справа карточка выбранной заявки.
 * Фильтры живут в адресе, поэтому сохраняются при переходе между заявками и по ссылке.
 * На телефоне список и карточка показываются по очереди.
 */
export default function CrmPage() {
  const { push } = useToast()
  const navigate = useNavigate()
  const leadId = useMatch('/admin/crm/:leadId')?.params.leadId
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
  const items = data?.items ?? []
  const sources = data?.sources ?? []
  const assignees = assigneesQuery.data?.items ?? []
  const hasFilters = params.q !== '' || params.status !== 'all' || params.source !== ''
    || params.from !== '' || params.to !== '' || params.assignee !== 'all'
  const hasExtraFilters = params.from !== '' || params.to !== '' || params.sort !== 'createdAt' || params.direction !== 'desc'

  const leadHref = (id: string) => withSearch(`/admin/crm/${id}`, searchParams)
  const listHref = withSearch('/admin/crm', searchParams)

  // J / K — следующая и предыдущая заявка в списке, как в почтовых клиентах.
  const moveSelection = (step: 1 | -1) => {
    if (items.length === 0) {
      return
    }
    const currentIndex = items.findIndex((item) => item.id === leadId)
    const nextIndex = currentIndex === -1
      ? (step === 1 ? 0 : items.length - 1)
      : Math.min(items.length - 1, Math.max(0, currentIndex + step))
    const next = items[nextIndex]
    if (next !== undefined && next.id !== leadId) {
      navigate(leadHref(next.id))
    }
  }
  useHotkeys('j', () => moveSelection(1), [items, leadId, searchParams])
  useHotkeys('k', () => moveSelection(-1), [items, leadId, searchParams])

  const selected = leadId !== undefined && leadId !== ''

  return (
    <div className="grid gap-4">
      <PageHeader
        title="Заявки"
        description="Заявки с сайта: поиск, фильтры, ответственные и история работы с клиентом. J / K — следующая и предыдущая заявка."
        actions={(
          <Button type="button" variant="outline" disabled={exporting} onClick={() => void exportCsv()}>
            Экспорт CSV
          </Button>
        )}
      />

      <div className="grid gap-5 lg:grid-cols-[minmax(320px,400px)_minmax(0,1fr)]">
        <section aria-label="Список заявок" className={cn('grid content-start gap-3', selected && 'hidden lg:grid')}>
          <div className="flex flex-wrap gap-1.5" role="group" aria-label="Фильтр по статусу">
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

          <div className="grid gap-2 sm:grid-cols-2">
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

          <details className="rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800" open={hasExtraFilters}>
            <summary className="cursor-pointer select-none font-medium text-slate-700 dark:text-slate-300">Период и сортировка</summary>
            <div className="mt-3 grid gap-2">
              <div className="grid grid-cols-2 gap-2">
                <label className="grid gap-1 text-xs text-slate-500 dark:text-slate-400">
                  С
                  <Input type="date" aria-label="Дата от" value={params.from} onChange={(event) => update({ from: event.target.value })} />
                </label>
                <label className="grid gap-1 text-xs text-slate-500 dark:text-slate-400">
                  По
                  <Input type="date" aria-label="Дата до" value={params.to} onChange={(event) => update({ to: event.target.value })} />
                </label>
              </div>
              <div className="flex flex-wrap items-center gap-2">
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
              </div>
            </div>
          </details>

          {hasFilters ? (
            <div>
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
            </div>
          ) : null}

          {leadsQuery.isError ? (
            <ErrorState
              title="Не удалось загрузить заявки"
              description="Проверьте endpoint /admin/api/leads и право leads.view."
            />
          ) : null}

          {leadsQuery.isPending ? <p className="text-sm text-slate-500">Загрузка...</p> : null}

          {data !== undefined && items.length === 0 ? (
            <EmptyState
              title={hasFilters ? 'Ничего не найдено' : 'Заявок пока нет'}
              description={hasFilters ? 'Измените поисковый запрос или сбросьте фильтры.' : 'Новые заявки с сайта появятся здесь.'}
            />
          ) : null}

          {items.length > 0 ? (
            <>
              <ul className="grid gap-1" aria-label="Заявки">
                {items.map((lead) => {
                  const isCurrent = lead.id === leadId

                  return (
                    <li
                      key={lead.id}
                      data-testid="lead-row"
                      className={cn(
                        'relative rounded-xl border px-3 py-3 transition',
                        isCurrent
                          ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20'
                          : 'border-transparent hover:bg-slate-50 dark:hover:bg-slate-800/60',
                      )}
                    >
                      <div className="flex items-baseline gap-2">
                        <Link
                          to={leadHref(lead.id)}
                          aria-current={isCurrent ? 'page' : undefined}
                          className={cn(
                            'min-w-0 flex-1 truncate text-sm after:absolute after:inset-0 after:rounded-xl focus-visible:outline-hidden focus-visible:after:ring-2 focus-visible:after:ring-emerald-500',
                            lead.status === 'new' ? 'font-semibold' : 'font-medium',
                          )}
                        >
                          {lead.name}
                        </Link>
                        <time dateTime={lead.createdAt} className="shrink-0 text-xs text-slate-500 dark:text-slate-400">{formatDateTime(lead.createdAt)}</time>
                      </div>
                      {lead.messagePreview !== null ? (
                        <p className="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-300">{lead.messagePreview}</p>
                      ) : null}
                      <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                        <Badge tone={leadStatusTone(lead.status)}>{leadStatusLabel(lead.status)}</Badge>
                        <span>{lead.phone}</span>
                        <span>{leadSourceLabel(lead.source)}</span>
                        {lead.assignee !== null ? <span>{lead.assignee.email ?? 'Пользователь удалён'}</span> : null}
                      </div>
                    </li>
                  )
                })}
              </ul>
              {data !== undefined ? (
                <Pagination page={data.page} pages={data.pages} total={data.total} onPageChange={(page) => update({ page })} />
              ) : null}
            </>
          ) : null}
        </section>

        <section aria-label="Карточка заявки" className={cn('min-w-0', !selected && 'hidden lg:block')}>
          {selected ? (
            <LeadDetailPage key={leadId} leadId={leadId} closeHref={listHref} />
          ) : (
            <div className="flex min-h-80 flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
              <p className="text-base font-semibold">Выберите заявку в списке</p>
              <p className="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                Здесь откроется карточка: запрос клиента, звонок в один клик, статус, ответственный и история.
              </p>
            </div>
          )}
        </section>
      </div>
    </div>
  )
}
