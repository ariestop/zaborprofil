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
import { leadSourceLabel, leadStatusLabel } from '../entities/lead/model'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { formatDateTime } from '../shared/lib/format'
import { Badge, Button, Card, ErrorState, PageHeader, PageLoadingState, Select, Textarea } from '../shared/ui'
import type { LeadEvent, LeadStatus } from '../types/api'
import { statusTone } from './CrmPage'

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

export default function LeadDetailPage() {
  const { leadId = '' } = useParams()
  const { push } = useToast()
  const leadQuery = useLeadQuery(leadId)
  const assigneesQuery = useLeadAssigneesQuery()
  const statusMutation = useLeadStatusMutation()
  const assigneeMutation = useLeadAssigneeMutation()
  const noteMutation = useLeadNoteMutation()
  const [note, setNote] = useState('')

  if (leadQuery.isPending) {
    return <PageLoadingState />
  }

  if (leadQuery.isError || leadQuery.data === undefined) {
    return (
      <div className="grid gap-4">
        <Link className="text-sm text-emerald-700 hover:underline dark:text-emerald-400" to="/admin/crm">← К списку заявок</Link>
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

  return (
    <div className="grid gap-4">
      <Link className="text-sm text-emerald-700 hover:underline dark:text-emerald-400" to="/admin/crm">← К списку заявок</Link>
      <PageHeader
        title={lead.name}
        description={`Заявка от ${formatDateTime(lead.createdAt)} · ${leadSourceLabel(lead.source)}`}
        actions={<Badge tone={statusTone(lead.status)}>{leadStatusLabel(lead.status)}</Badge>}
      />

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="grid gap-4 lg:col-span-2">
          <Card title="Заявка">
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Телефон</dt>
                <dd className="font-medium">{lead.phone}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Email</dt>
                <dd className="font-medium">{lead.email ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Spam score</dt>
                <dd className="font-medium">
                  {lead.spamScore}
                  {lead.spamReasons.length > 0 ? ` · ${lead.spamReasons.join(', ')}` : ''}
                </dd>
              </div>
              <div>
                <dt className="text-slate-500 dark:text-slate-400">Обновлена</dt>
                <dd className="font-medium">{formatDateTime(lead.updatedAt)}</dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-slate-500 dark:text-slate-400">Сообщение</dt>
                <dd className="whitespace-pre-wrap break-words">{lead.message ?? '—'}</dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-slate-500 dark:text-slate-400">Согласие на обработку данных</dt>
                <dd className="break-words font-mono text-xs">{JSON.stringify(lead.consentSnapshot)}</dd>
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

          <Card title="Заметка менеджера">
            <div className="grid gap-2">
              <Textarea
                aria-label="Текст заметки"
                maxLength={NOTE_MAX_LENGTH}
                placeholder="Итог разговора, договорённости, следующий шаг"
                value={note}
                onChange={(event) => setNote(event.target.value)}
              />
              <div className="flex items-center justify-between">
                <span className="text-xs text-slate-500 dark:text-slate-400">{note.length} / {NOTE_MAX_LENGTH}</span>
                <Button type="button" disabled={note.trim() === '' || noteMutation.isPending} onClick={() => void submitNote()}>
                  Добавить заметку
                </Button>
              </div>
            </div>
          </Card>
        </div>
      </div>
    </div>
  )
}
