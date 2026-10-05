import { useState } from 'react'
import { Badge, Button, Card, ConfirmDialog, EmptyState, ErrorState, Input, Select, Table } from '../../../shared/ui'
import { Pagination } from '../../../shared/ui/pagination'
import { useToast } from '../../../app/providers/toast-provider'
import { useClearNotFoundMutation, useDeleteNotFoundMutation, useNotFoundQuery } from '../../../entities/seo/api'
import type { NotFoundEntry, NotFoundListParams, NotFoundSort } from '../../../entities/seo/model'
import { useDebouncedValue } from '../../../shared/hooks/use-debounced-value'
import { formatDateTime, formatNumber } from '../../../shared/lib/format'
import { RedirectFormDialog, type RedirectDraft } from '../redirects/RedirectFormDialog'
import { decodeForDisplay, describeApiError } from '../redirects/redirect-rules'

const PER_PAGE = 25
const STALE_DAYS = 90

const sortOptions: Array<{ value: NotFoundSort, label: string }> = [
  { value: 'hits', label: 'Сначала частые' },
  { value: 'lastSeen', label: 'Сначала свежие' },
]

type ClearTarget = { days: number | null } | null

export function NotFoundTab() {
  const { push } = useToast()
  const [search, setSearch] = useState('')
  const [sort, setSort] = useState<NotFoundSort>('hits')
  const [page, setPage] = useState(1)
  const [draft, setDraft] = useState<RedirectDraft | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<NotFoundEntry | null>(null)
  const [clearTarget, setClearTarget] = useState<ClearTarget>(null)

  const debouncedSearch = useDebouncedValue(search)
  const params: NotFoundListParams = { q: debouncedSearch, sort, page, perPage: PER_PAGE }
  const notFoundQuery = useNotFoundQuery(params)
  const deleteMutation = useDeleteNotFoundMutation()
  const clearMutation = useClearNotFoundMutation()

  const confirmDelete = async () => {
    if (deleteTarget === null) {
      return
    }

    try {
      await deleteMutation.mutateAsync(deleteTarget.id)
      push({ title: 'Запись удалена', description: decodeForDisplay(deleteTarget.path) })
    } catch (error) {
      push({ title: 'Не удалось удалить запись', description: describeApiError(error, 'Повторите попытку.') })
    } finally {
      setDeleteTarget(null)
    }
  }

  const confirmClear = async () => {
    if (clearTarget === null) {
      return
    }

    try {
      const result = await clearMutation.mutateAsync(clearTarget.days)
      setPage(1)
      push({ title: 'Журнал очищен', description: `Удалено записей: ${result.removed}.` })
    } catch (error) {
      push({ title: 'Не удалось очистить журнал', description: describeApiError(error, 'Повторите попытку.') })
    } finally {
      setClearTarget(null)
    }
  }

  const data = notFoundQuery.data

  return (
    <div className="grid gap-4">
      <Card
        title="Журнал 404"
        description="Адреса, которые посетители и роботы запрашивали, но не нашли. Частые обращения к старым URL — кандидаты на редирект. Параметры запроса не сохраняются."
      >
        <div className="grid gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <div className="w-full max-w-sm">
              <Input
                type="search"
                aria-label="Поиск по журналу 404"
                placeholder="Поиск по адресу"
                value={search}
                onChange={(event) => {
                  setSearch(event.target.value)
                  setPage(1)
                }}
              />
            </div>
            <Select value={sort} onValueChange={(value) => { setSort(value as NotFoundSort); setPage(1) }} options={sortOptions} />
            <Button type="button" variant="outline" size="sm" onClick={() => setClearTarget({ days: STALE_DAYS })}>Очистить старше {STALE_DAYS} дней</Button>
            <Button type="button" variant="outline" size="sm" onClick={() => setClearTarget({ days: null })}>Очистить всё</Button>
          </div>

          {data !== undefined ? (
            <p className="text-sm text-graphite dark:text-slate-300">
              Адресов: {formatNumber(data.total)} · обращений: {formatNumber(data.totalHits)}
            </p>
          ) : null}

          {notFoundQuery.isError ? <ErrorState title="Не удалось загрузить журнал 404" description="Проверьте endpoint /admin/api/seo/not-found." /> : null}
          {notFoundQuery.isPending ? <p className="text-sm text-graphite dark:text-slate-500">Загрузка...</p> : null}

          {data !== undefined && data.items.length === 0 ? (
            <EmptyState title="Журнал пуст" description="Ошибок 404 на публичной части сайта пока не зафиксировано." />
          ) : null}

          {data !== undefined && data.items.length > 0 ? (
            <>
              <Table
                head={(
                  <tr>
                    {['Адрес', 'Обращений', 'Последнее', 'Источник перехода', 'Действия'].map((title) => (
                      <th key={title} className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-graphite dark:text-slate-500">{title}</th>
                    ))}
                  </tr>
                )}
                body={data.items.map((entry) => (
                  <tr key={entry.id} data-testid="not-found-row">
                    <td className="px-3 py-2 text-sm font-mono break-all" title={entry.path}>
                      {decodeForDisplay(entry.path)}
                      {entry.hasRedirect ? <> <Badge tone="success">Редирект есть</Badge></> : null}
                    </td>
                    <td className="px-3 py-2 text-sm">{formatNumber(entry.hitCount)}</td>
                    <td className="px-3 py-2 text-sm">{formatDateTime(entry.lastSeenAt)}</td>
                    <td className="px-3 py-2 text-sm break-all">{entry.referrer ?? '—'}</td>
                    <td className="px-3 py-2 text-sm">
                      <div className="flex gap-2">
                        <Button type="button" size="sm" variant="outline" disabled={entry.hasRedirect} onClick={() => setDraft({ sourcePath: entry.path })}>
                          Создать редирект
                        </Button>
                        <Button type="button" size="sm" variant="ghost" onClick={() => setDeleteTarget(entry)}>Удалить</Button>
                      </div>
                    </td>
                  </tr>
                ))}
              />
              <Pagination page={data.page} pages={data.pages} total={data.total} onPageChange={setPage} />
            </>
          ) : null}
        </div>
      </Card>

      <RedirectFormDialog open={draft !== null} redirect={null} draft={draft} onClose={() => setDraft(null)} />
      <ConfirmDialog
        open={deleteTarget !== null}
        title="Удалить запись из журнала?"
        description={deleteTarget === null ? undefined : `${decodeForDisplay(deleteTarget.path)} исчезнет из журнала. Если адрес снова запросят, он будет записан заново.`}
        confirmLabel="Удалить"
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteTarget(null)}
      />
      <ConfirmDialog
        open={clearTarget !== null}
        title="Очистить журнал 404?"
        description={clearTarget?.days === null ? 'Будут удалены все записи журнала.' : `Будут удалены записи, к которым не обращались более ${STALE_DAYS} дней.`}
        confirmLabel="Очистить"
        onConfirm={() => void confirmClear()}
        onCancel={() => setClearTarget(null)}
      />
    </div>
  )
}
