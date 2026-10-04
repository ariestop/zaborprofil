import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Badge, Button, Card, Dialog, EmptyState, ErrorState, PageLoadingState, Table } from '../../../shared/ui'
import { useToast } from '../../../app/providers/toast-provider'
import { fetchPageSeoAudit } from '../../../entities/seo/api'
import type { SeoAuditResult } from '../../../entities/seo/model'
import { usePagesQuery } from '../../../entities/page/api'
import type { ContentPageItem } from '../../../types/api'
import { describeApiError } from '../redirects/redirect-rules'
import { countIssues, severityTone } from './audit-summary'

type AuditState = { status: 'loading' } | { status: 'error' } | { status: 'done', result: SeoAuditResult }

const STATUS_LABELS: Record<ContentPageItem['status'], string> = {
  draft: 'Черновик',
  review: 'На проверке',
  approved: 'Утверждена',
  published: 'Опубликована',
  scheduled: 'Запланирована',
  unpublished: 'Снята',
  archived: 'Архив',
  deleted: 'Удалена',
}

export function AuditTab() {
  const { push } = useToast()
  const pagesQuery = usePagesQuery()
  const [audits, setAudits] = useState<Record<string, AuditState>>({})
  const [running, setRunning] = useState(false)
  const [detailsId, setDetailsId] = useState<string | null>(null)

  const pages = useMemo(
    () => (pagesQuery.data ?? []).filter((page) => page.status !== 'deleted'),
    [pagesQuery.data],
  )

  const runAudit = async (pageId: string) => {
    setAudits((current) => ({ ...current, [pageId]: { status: 'loading' } }))
    try {
      const result = await fetchPageSeoAudit(pageId)
      setAudits((current) => ({ ...current, [pageId]: { status: 'done', result } }))
    } catch {
      setAudits((current) => ({ ...current, [pageId]: { status: 'error' } }))
    }
  }

  const runPublished = async () => {
    const published = pages.filter((page) => page.status === 'published')
    if (published.length === 0) {
      push({ title: 'Нет опубликованных страниц', description: 'Проверять пока нечего.' })
      return
    }

    setRunning(true)
    try {
      for (const page of published) {
        await runAudit(page.id)
      }
    } catch (error) {
      push({ title: 'Проверка прервана', description: describeApiError(error, 'Повторите попытку.') })
    } finally {
      setRunning(false)
    }
  }

  if (pagesQuery.isPending) {
    return <PageLoadingState />
  }

  if (pagesQuery.isError) {
    return <ErrorState title="Не удалось загрузить страницы" description="Проверьте endpoint /admin/api/content/pages." />
  }

  const details = detailsId === null ? undefined : audits[detailsId]
  const detailsPage = pages.find((page) => page.id === detailsId)
  const checkedCount = Object.values(audits).filter((audit) => audit.status === 'done').length

  return (
    <div className="grid gap-4">
      <Card
        title="SEO-аудит страниц"
        description="Тот же аудит, что блокирует публикацию: P0/P1 — обязательно исправить, P2 — рекомендации (title, description, OpenGraph, контент)."
      >
        <div className="grid gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <Button type="button" disabled={running} onClick={() => void runPublished()}>
              {running ? 'Проверка...' : 'Проверить все опубликованные'}
            </Button>
            <span className="text-sm text-slate-600 dark:text-slate-300">Проверено страниц: {checkedCount} из {pages.length}</span>
          </div>

          {pages.length === 0 ? (
            <EmptyState title="Страниц пока нет" description="Создайте страницу в разделе «Страницы», чтобы запустить аудит." />
          ) : (
            <Table
              head={(
                <tr>
                  {['Страница', 'Путь', 'Статус', 'Результат аудита', 'Действия'].map((title) => (
                    <th key={title} className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</th>
                  ))}
                </tr>
              )}
              body={pages.map((page) => {
                const audit = audits[page.id]
                return (
                  <tr key={page.id} data-testid="audit-row">
                    <td className="px-3 py-2 text-sm">{page.title}</td>
                    <td className="px-3 py-2 text-sm font-mono break-all">{page.path}</td>
                    <td className="px-3 py-2 text-sm">{STATUS_LABELS[page.status]}</td>
                    <td className="px-3 py-2 text-sm">
                      <AuditSummary state={audit} />
                    </td>
                    <td className="px-3 py-2 text-sm">
                      <div className="flex flex-wrap gap-2">
                        <Button type="button" size="sm" variant="outline" disabled={audit?.status === 'loading'} onClick={() => void runAudit(page.id)}>
                          Проверить
                        </Button>
                        {audit?.status === 'done' && audit.result.issues.length > 0 ? (
                          <Button type="button" size="sm" variant="ghost" onClick={() => setDetailsId(page.id)}>Замечания</Button>
                        ) : null}
                        <Link className="inline-flex h-8 items-center text-sm text-emerald-700 underline dark:text-emerald-400" to={`/admin/pages/${page.id}`}>
                          Открыть
                        </Link>
                      </div>
                    </td>
                  </tr>
                )
              })}
            />
          )}
        </div>
      </Card>

      <Dialog
        open={detailsPage !== undefined && details?.status === 'done'}
        onOpenChange={(open) => {
          if (!open) {
            setDetailsId(null)
          }
        }}
        title={detailsPage === undefined ? 'Замечания' : `Замечания: ${detailsPage.title}`}
        description={detailsPage?.path}
        contentClassName="max-w-2xl"
      >
        {details?.status === 'done' ? (
          <ul className="grid max-h-96 gap-2 overflow-y-auto text-sm" aria-label="Замечания SEO-аудита">
            {details.result.issues.map((issue, index) => (
              <li key={`${issue.code}-${index}`} className="flex items-start gap-2">
                <Badge tone={severityTone(issue.severity)}>{issue.severity}</Badge>
                <span>{issue.message} <span className="font-mono text-xs text-slate-500">({issue.code})</span></span>
              </li>
            ))}
          </ul>
        ) : null}
        <div className="mt-4 flex justify-end">
          <Button type="button" variant="ghost" onClick={() => setDetailsId(null)}>Закрыть</Button>
        </div>
      </Dialog>
    </div>
  )
}

function AuditSummary({ state }: { state: AuditState | undefined }) {
  if (state === undefined) {
    return <span className="text-slate-400">Не проверялась</span>
  }

  if (state.status === 'loading') {
    return <span className="text-slate-500">Проверка...</span>
  }

  if (state.status === 'error') {
    return <span className="text-red-600">Ошибка проверки</span>
  }

  const counts = countIssues(state.result.issues)
  if (state.result.issues.length === 0) {
    return <Badge tone="success">Замечаний нет</Badge>
  }

  return (
    <span className="inline-flex flex-wrap gap-1">
      {counts.P0 > 0 ? <Badge tone="warning">P0: {counts.P0}</Badge> : null}
      {counts.P1 > 0 ? <Badge tone="warning">P1: {counts.P1}</Badge> : null}
      {counts.P2 > 0 ? <Badge>P2: {counts.P2}</Badge> : null}
      {state.result.passed ? <Badge tone="success">Публикация разрешена</Badge> : <Badge tone="warning">Блокирует публикацию</Badge>}
    </span>
  )
}
