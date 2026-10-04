import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useToast } from '../app/providers/toast-provider'
import {
  useLeadAssigneeMutation,
  useLeadAssigneesQuery,
  useLeadNoteMutation,
  useLeadQuery,
  useLeadStatusMutation,
} from '../entities/lead/api'
import { leadSourceLabel, leadStatusLabel, leadStatusTone as statusTone, nextLeadAction } from '../entities/lead/model'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { NavIcon } from '../layouts/nav-icons'
import { formatDateTime } from '../shared/lib/format'
import { Badge, Button, Card, ErrorState, PageLoadingState, Select, Textarea } from '../shared/ui'
import type { LeadEvent, LeadStatus } from '../types/api'

const NOTE_MAX_LENGTH = 2000
const STATUSES: LeadStatus[] = ['new', 'in_progress', 'done', 'spam']
const NO_ASSIGNEE = 'none'

function describeEvent(event: LeadEvent): string {
  if (event.type === 'note') {
    return event.body ?? ''
  }

  if (event.type === 'status_changed') {
    const from = typeof event.data.from === 'string' ? leadStatusLabel(event.data.from) : '—'
    const to = typeof event.data.to === 'string' ? leadStatusLabel(event.data.to) : '—'

    return `Статус изменён: ${from} → ${to}`
  }

  const target = typeof event.data.toLabel === 'string' ? event.data.toLabel : null

  return target === null ? 'Ответственный снят' : `Назначен ответственный: ${target}`
}

const eventTitles: Record<LeadEvent['type'], string> = {
  note: 'Заметка',
  status_changed: 'Статус',
  assigned: 'Назначение',
}

function phoneHref(phone: string): string {
  return `tel:${phone.replace(/[^\d+]/g, '')}`
}

function hasConsent(snapshot: Record<string, unknown> | null | undefined): boolean {
  return snapshot !== null && snapshot !== undefined && Object.keys(snapshot).length > 0
}

interface LeadDetailPageProps {
  /** Идентификатор заявки; по умолчанию берётся из адреса `/admin/crm/:leadId`. */
  leadId?: string
  /** Куда ведёт кнопка «Закрыть» в режиме панели рядом со списком. */
  closeHref?: string
}

/**
 * Карточка заявки. На широком экране показывается справа от списка в разделе «Заявки»,
 * на телефоне — отдельным экраном со ссылкой назад к списку.
 */
export default function LeadDetailPage({ leadId: leadIdProp, closeHref }: LeadDetailPageProps = {}) {
  const params = useParams()
  const leadId = leadIdProp ?? params.leadId ?? ''
  const { push } = useToast()
  const leadQuery = useLeadQuery(leadId)
  const assigneesQuery = useLeadAssigneesQuery()
  const statusMutation = useLeadStatusMutation()
  const assigneeMutation = useLeadAssigneeMutation()
  const noteMutation = useLeadNoteMutation()
  const [note, setNote] = useState('')
  const backHref = closeHref ?? '/admin/crm'

  if (leadQuery.isPending) {
    return <PageLoadingState />
  }

  if (leadQuery.isError || leadQuery.data === undefined) {
    return (
      <div className="grid gap-4">
        <Link className="text-sm text-emerald-700 hover:underline dark:text-emerald-400" to={backHref}>← К списку заявок</Link>
        <ErrorState title="Не удалось загрузить заявку" description="Заявка не найдена или нет права leads.view." />
      </div>
    )
  }

  const lead = leadQuery.data
  const assignees = assigneesQuery.data?.items ?? []
  const assigneeOptions = [
    { value: NO_ASSIGNEE, label: 'Не назначен' },
    ...assignees.map((assignee) => ({ value: assignee.id, label: assignee.email })),
  ]
  if (lead.assignee !== null && !assignees.some((assignee) => assignee.id === lead.assignee?.id)) {
    assigneeOptions.push({ value: lead.assignee.id, label: lead.assignee.email ?? 'Пользователь удалён' })
  }
  const nextAction = nextLeadAction(lead.status)

  const changeStatus = async (status: string) => {
    try {
      await statusMutation.mutateAsync({ leadId: lead.id, status: status as LeadStatus })
      push({ title: 'Статус обновлён', description: leadStatusLabel(status) })
    } catch (error) {
      push({ title: 'Не удалось изменить статус', description: describeApiError(error, 'Повторите попытку.') })
    }
  }

  const changeAssignee = async (value: string) => {
    try {
      await assigneeMutation.mutateAsync({ leadId: lead.id, assigneeId: value === NO_ASSIGNEE ? null : value })
      push({ title: 'Ответственный обновлён' })
    } catch (error) {
      push({ title: 'Не удалось назначить ответственного', description: describeApiError(error, 'Повторите попытку.') })
    }
  }

  const submitNote = async () => {
    const text = note.trim()
    if (text === '') {
      return
    }

    try {
      await noteMutation.mutateAsync({ leadId: lead.id, text })
      setNote('')
      push({ title: 'Заметка добавлена' })
    } catch (error) {
      push({ title: 'Не удалось сохранить заметку', description: describeApiError(error, 'Повторите попытку.') })
    }
  }

  const secondaryAction = 'inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-800 transition hover:bg-slate-50 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800'

  return (
    <div className="grid gap-4" data-testid="lead-detail">
      <Link className="text-sm text-emerald-700 hover:underline lg:hidden dark:text-emerald-400" to={backHref}>← К списку заявок</Link>

      <header className="flex flex-wrap items-start gap-x-4 gap-y-3">
        <span
          aria-hidden="true"
          className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-100 text-base font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
        >
          {lead.name.trim().charAt(0).toUpperCase() || '?'}
        </span>
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-xl font-semibold">{lead.name}</h2>
            <Badge tone={statusTone(lead.status)}>{leadStatusLabel(lead.status)}</Badge>
          </div>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {formatDateTime(lead.createdAt)} · {leadSourceLabel(lead.source)}
          </p>
        </div>
        {closeHref !== undefined ? (
          <Link
            to={closeHref}
            aria-label="Закрыть карточку заявки"
            className="hidden h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:inline-flex dark:hover:bg-slate-800"
          >
            <NavIcon name="close" />
          </Link>
        ) : null}
      </header>

      <div className="flex flex-wrap gap-2" aria-label="Быстрые действия">
        <a className={secondaryAction} href={phoneHref(lead.phone)}>
          <NavIcon name="phone" size={16} />
          Позвонить
        </a>
        {lead.email !== null ? (
          <a className={secondaryAction} href={`mailto:${lead.email}`}>
            <NavIcon name="mail" size={16} />
            Написать
          </a>
        ) : null}
        {nextAction !== null ? (
          <Button
            type="button"
            className="h-10"
            disabled={statusMutation.isPending}
            onClick={() => void changeStatus(nextAction.status)}
          >
            {nextAction.label}
          </Button>
        ) : null}
      </div>

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_280px]">
        <div className="grid content-start gap-4">
          <Card title="Запрос клиента">
            <p className="whitespace-pre-wrap break-words text-[15px] leading-relaxed">{lead.message ?? 'Клиент не оставил сообщения.'}</p>
            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Телефон</dt>
                <dd className="font-medium">{lead.phone}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Email</dt>
                <dd className="font-medium break-all">{lead.email ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Источник</dt>
                <dd className="font-medium">{leadSourceLabel(lead.source)}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Обновлена</dt>
                <dd className="font-medium">{formatDateTime(lead.updatedAt)}</dd>
              </div>
            </dl>
          </Card>

          <Card title="История" description="Смена статуса, назначения и заметки менеджеров.">
            {lead.events.length === 0 ? (
              <p className="text-sm text-slate-500 dark:text-slate-400">Событий пока нет.</p>
            ) : (
              <ol className="grid gap-3" aria-label="История заявки">
                {lead.events.map((event) => (
                  <li key={event.id} data-testid="lead-event" className="rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-800">
                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
                      <span>{eventTitles[event.type]} · {event.actorLabel}</span>
                      <time dateTime={event.createdAt}>{formatDateTime(event.createdAt)}</time>
                    </div>
                    <p className="mt-1 whitespace-pre-wrap break-words">{describeEvent(event)}</p>
                  </li>
                ))}
              </ol>
            )}
            <div className="mt-4 grid gap-2">
              <Textarea
                aria-label="Текст заметки"
                maxLength={NOTE_MAX_LENGTH}
                placeholder="Итог разговора, договорённости, следующий шаг"
                value={note}
                onChange={(event) => setNote(event.target.value)}
              />
              <div className="flex items-center justify-between">
                <span className="text-xs text-slate-500 dark:text-slate-400">{note.length} / {NOTE_MAX_LENGTH}</span>
                <Button type="button" variant="outline" disabled={note.trim() === '' || noteMutation.isPending} onClick={() => void submitNote()}>
                  Добавить заметку
                </Button>
              </div>
            </div>
          </Card>
        </div>

        <div className="grid content-start gap-4">
          <Card title="Работа с заявкой">
            <div className="grid gap-4">
              <div className="grid gap-1">
                <span className="text-sm text-slate-500 dark:text-slate-400">Статус</span>
                <Select
                  value={lead.status}
                  onValueChange={(status) => void changeStatus(status)}
                  options={STATUSES.map((status) => ({ value: status, label: leadStatusLabel(status) }))}
                />
              </div>
              <div className="grid gap-1">
                <span className="text-sm text-slate-500 dark:text-slate-400">Ответственный</span>
                <Select
                  value={lead.assignee?.id ?? NO_ASSIGNEE}
                  onValueChange={(value) => void changeAssignee(value)}
                  options={assigneeOptions}
                />
              </div>
            </div>
          </Card>

          <Card title="Антиспам">
            <p className={lead.spamScore >= 5 ? 'text-sm font-semibold text-red-700 dark:text-red-400' : 'text-sm font-semibold text-emerald-700 dark:text-emerald-400'}>
              {lead.spamScore >= 5 ? 'Похоже на спам' : 'Подозрений нет'}
            </p>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Спам-балл: {lead.spamScore}
              {lead.spamReasons.length > 0 ? ` · ${lead.spamReasons.join(', ')}` : ''}
            </p>
          </Card>

          <Card title="Согласие на обработку данных">
            <p className={hasConsent(lead.consentSnapshot) ? 'text-sm font-semibold text-emerald-700 dark:text-emerald-400' : 'text-sm font-semibold text-amber-700 dark:text-amber-400'}>
              {hasConsent(lead.consentSnapshot) ? 'Получено при отправке формы' : 'Нет данных о согласии'}
            </p>
            {hasConsent(lead.consentSnapshot) ? (
              <details className="mt-2 text-sm">
                <summary className="cursor-pointer text-slate-500 dark:text-slate-400">Сохранённый снимок</summary>
                <pre className="mt-2 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-2 font-mono text-xs dark:bg-slate-950">
                  {JSON.stringify(lead.consentSnapshot, null, 2)}
                </pre>
              </details>
            ) : null}
          </Card>
        </div>
      </div>
    </div>
  )
}
