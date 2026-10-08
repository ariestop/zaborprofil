import { useState } from 'react'
import { Badge, Button, Card, ConfirmDialog, EmptyState, ErrorState, Input, Select, Switch, Table } from '../../../shared/ui'
import { Pagination } from '../../../shared/ui/pagination'
import { useToast } from '../../../app/providers/toast-provider'
import {
  exportRedirectsCsv,
  useDeleteRedirectMutation,
  useRedirectsQuery,
  useUpdateRedirectMutation,
} from '../../../entities/seo/api'
import type { RedirectListParams, RedirectSort, RedirectStatusFilter } from '../../../entities/seo/model'
import { useDebouncedValue } from '../../../shared/hooks/use-debounced-value'
import { downloadTextFile } from '../../../shared/lib/download'
import { formatDateTime, formatNumber } from '../../../shared/lib/format'
import type { RedirectItem } from '../../../types/api'
import { RedirectAnalysisCard } from './RedirectAnalysisCard'
import { RedirectFormDialog } from './RedirectFormDialog'
import { RedirectImportDialog } from './RedirectImportDialog'
import { decodeForDisplay, describeApiError } from './redirect-rules'

const PER_PAGE = 25

const statusOptions: Array<{ value: RedirectStatusFilter, label: string }> = [
  { value: 'all', label: 'Все' },
  { value: 'active', label: 'Активные' },
  { value: 'inactive', label: 'Отключённые' },
]

const sortOptions: Array<{ value: RedirectSort, label: string }> = [
  { value: 'source', label: 'По старому URL' },
  { value: 'hits', label: 'По числу переходов' },
  { value: 'lastHit', label: 'По последнему переходу' },
  { value: 'updated', label: 'По дате изменения' },
]

export function RedirectsTab() {
  const { push } = useToast()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<RedirectStatusFilter>('all')
  const [sort, setSort] = useState<RedirectSort>('source')
  const [descending, setDescending] = useState(false)
  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<RedirectItem | null>(null)
  const [importOpen, setImportOpen] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<RedirectItem | null>(null)

  const debouncedSearch = useDebouncedValue(search)
  const params: RedirectListParams = {
    q: debouncedSearch,
    status,
    sort,
    direction: descending ? 'desc' : 'asc',
    page,
    perPage: PER_PAGE,
  }
  const redirectsQuery = useRedirectsQuery(params)
  const updateMutation = useUpdateRedirectMutation()
  const deleteMutation = useDeleteRedirectMutation()

  const openCreate = () => {
    setEditing(null)
    setFormOpen(true)
  }

  const openEdit = (redirect: RedirectItem) => {
    setEditing(redirect)
    setFormOpen(true)
  }

  const toggleActive = async (redirect: RedirectItem) => {
    try {
      const saved = await updateMutation.mutateAsync({
        id: redirect.id,
        payload: { targetPath: redirect.targetPath, statusCode: redirect.statusCode, isActive: !redirect.isActive },
      })
      push({
        title: saved.isActive ? 'Редирект включён' : 'Редирект отключён',
        description: saved.warnings.map((warning) => warning.message).join(' ') || decodeForDisplay(saved.sourcePath),
      })
    } catch (error) {
      push({ title: 'Не удалось изменить статус', description: describeApiError(error, 'Повторите попытку.') })
    }
  }

  const confirmDelete = async () => {
    if (deleteTarget === null) {
      return
    }

    try {
      await deleteMutation.mutateAsync(deleteTarget.id)
      push({ title: 'Редирект удалён', description: decodeForDisplay(deleteTarget.sourcePath) })
    } catch (error) {
      push({ title: 'Не удалось удалить редирект', description: describeApiError(error, 'Повторите попытку.') })
    } finally {
      setDeleteTarget(null)
    }
  }

  const exportCsv = async () => {
    try {
      downloadTextFile('redirects.csv', await exportRedirectsCsv())
    } catch (error) {
      push({ title: 'Не удалось выгрузить CSV', description: describeApiError(error, 'Повторите попытку.') })
    }
  }

  const data = redirectsQuery.data
  const counts = data?.counts

  return (
    <div className="grid gap-4">
      <Card
        title="Редиректы"
        description="Сохраняют позиции старых адресов при ручном переносе страниц: посетитель и поисковый робот попадают на новый URL."
      >
        <div className="grid gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <Button type="button" onClick={openCreate}>Добавить редирект</Button>
            <Button type="button" variant="outline" onClick={() => setImportOpen(true)}>Импорт CSV</Button>
            <Button type="button" variant="outline" onClick={() => void exportCsv()}>Экспорт CSV</Button>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <div className="w-full max-w-sm">
              <Input
                type="search"
                aria-label="Поиск редиректов"
                placeholder="Поиск по старому или новому URL"
                value={search}
                onChange={(event) => {
                  setSearch(event.target.value)
                  setPage(1)
                }}
              />
            </div>
            <Select value={status} onValueChange={(value) => { setStatus(value as RedirectStatusFilter); setPage(1) }} options={statusOptions} />
            <Select value={sort} onValueChange={(value) => { setSort(value as RedirectSort); setPage(1) }} options={sortOptions} />
            <Button type="button" variant="outline" size="sm" aria-label="Направление сортировки" onClick={() => setDescending((current) => !current)}>
              {descending ? 'По убыванию ↓' : 'По возрастанию ↑'}
            </Button>
          </div>

          {counts !== undefined ? (
            <p className="text-sm text-graphite dark:text-slate-300">
              Всего: {formatNumber(counts.total)} · активных: {formatNumber(counts.active)} · отключённых: {formatNumber(counts.inactive)}
            </p>
          ) : null}

          {redirectsQuery.isError ? (
            <ErrorState title="Не удалось загрузить редиректы" description="Проверьте endpoint /admin/api/seo/redirects и право seo.edit." />
          ) : null}

          {redirectsQuery.isPending ? <p className="text-sm text-graphite dark:text-slate-500">Загрузка...</p> : null}

          {data !== undefined && data.items.length === 0 ? (
            <EmptyState
              title={debouncedSearch !== '' || status !== 'all' ? 'Ничего не найдено' : 'Редиректов пока нет'}
              description="Добавьте редирект вручную или загрузите карту старых URL из CSV."
            />
          ) : null}

          {data !== undefined && data.items.length > 0 ? (
            <>
              <Table
                head={(
                  <tr>
                    {['Старый URL', 'Новый URL', 'Код', 'Активен', 'Переходы', 'Последний переход', 'Действия'].map((title) => (
                      <th key={title} className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-graphite dark:text-slate-500">{title}</th>
                    ))}
                  </tr>
                )}
                body={data.items.map((redirect) => (
                  <tr key={redirect.id} data-testid="redirect-row">
                    <td className="px-3 py-2 text-sm font-mono break-all" title={redirect.sourcePath}>{decodeForDisplay(redirect.sourcePath)}</td>
                    <td className="px-3 py-2 text-sm font-mono break-all" title={redirect.targetPath}>{decodeForDisplay(redirect.targetPath)}</td>
                    <td className="px-3 py-2 text-sm"><Badge>{redirect.statusCode}</Badge></td>
                    <td className="px-3 py-2 text-sm">
                      <span aria-label={`Активность ${decodeForDisplay(redirect.sourcePath)}`}>
                        <Switch checked={redirect.isActive} onCheckedChange={() => void toggleActive(redirect)} />
                      </span>
                    </td>
                    <td className="px-3 py-2 text-sm">{formatNumber(redirect.hitCount)}</td>
                    <td className="px-3 py-2 text-sm">{formatDateTime(redirect.lastHitAt)}</td>
                    <td className="px-3 py-2 text-sm">
                      <div className="flex gap-2">
                        <Button type="button" size="sm" variant="outline" onClick={() => openEdit(redirect)}>Изменить</Button>
                        <Button type="button" size="sm" variant="danger" onClick={() => setDeleteTarget(redirect)}>Удалить</Button>
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

      <RedirectAnalysisCard />

      <RedirectFormDialog open={formOpen} redirect={editing} onClose={() => setFormOpen(false)} />
      <RedirectImportDialog open={importOpen} onClose={() => setImportOpen(false)} />
      <ConfirmDialog
        open={deleteTarget !== null}
        title="Удалить редирект?"
        description={deleteTarget === null ? undefined : `${decodeForDisplay(deleteTarget.sourcePath)} перестанет перенаправлять на ${decodeForDisplay(deleteTarget.targetPath)}. Если нужно временно, лучше отключить правило.`}
        confirmLabel="Удалить"
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  )
}
