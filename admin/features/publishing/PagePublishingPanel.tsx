import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useToast } from '../../app/providers/toast-provider'
import { ApiError } from '../../shared/api/client'
import { Badge } from '../../shared/ui/badge'
import { Button } from '../../shared/ui/button'
import { cancelPageSchedule, changePageStatus, fetchWorkflow, schedulePage, workflowQueryKey, type ScheduleInput } from './api'
import { formatDateTime } from './datetime'
import { eventLabels, statusActionLabels, statusLabels } from './labels'
import { ScheduleDialog } from './ScheduleDialog'
import type { PageWorkflow, WorkflowStatus, WorkflowTransition } from './types'

interface PagePublishingPanelProps {
  pageId: string
  /** Вызывается после любого изменения статуса или расписания, чтобы родитель перечитал страницу. */
  onChanged: () => Promise<void> | void
}

/** «Публикация» и «Удаление» остаются основными кнопками редактора страницы. */
const handledByEditor = new Set<WorkflowStatus>(['published', 'deleted'])

function errorMessage(caught: unknown, fallback: string): string {
  if (caught instanceof ApiError || caught instanceof Error) return caught.message

  return fallback
}

function statusTone(status: WorkflowStatus): 'neutral' | 'success' | 'warning' {
  if (status === 'published') return 'success'
  if (status === 'scheduled' || status === 'review') return 'warning'

  return 'neutral'
}

export function PagePublishingPanel({ pageId, onChanged }: PagePublishingPanelProps) {
  const queryClient = useQueryClient()
  const { push } = useToast()
  const [comment, setComment] = useState('')
  const [scheduleOpen, setScheduleOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [scheduleError, setScheduleError] = useState<string | null>(null)

  const workflowQuery = useQuery({ queryKey: workflowQueryKey(pageId), queryFn: () => fetchWorkflow(pageId) })

  const refresh = async (): Promise<void> => {
    await queryClient.invalidateQueries({ queryKey: workflowQueryKey(pageId) })
    await onChanged()
  }

  const statusMutation = useMutation({
    mutationFn: (status: WorkflowStatus) => changePageStatus(pageId, status, comment.trim() === '' ? null : comment.trim()),
    onSuccess: async (_page, status) => {
      setError(null)
      setComment('')
      push({ title: `Статус: ${statusLabels[status]}` })
      await refresh()
    },
    onError: (caught) => setError(errorMessage(caught, 'Не удалось изменить статус')),
  })

  const scheduleMutation = useMutation({
    mutationFn: (input: ScheduleInput) => schedulePage(pageId, input),
    onSuccess: async () => {
      setScheduleError(null)
      setScheduleOpen(false)
      push({ title: 'Расписание сохранено' })
      await refresh()
    },
    onError: (caught) => setScheduleError(errorMessage(caught, 'Не удалось сохранить расписание')),
  })

  const cancelMutation = useMutation({
    mutationFn: () => cancelPageSchedule(pageId, comment.trim() === '' ? null : comment.trim()),
    onSuccess: async () => {
      setError(null)
      setComment('')
      push({ title: 'Расписание отменено' })
      await refresh()
    },
    onError: (caught) => setError(errorMessage(caught, 'Не удалось отменить расписание')),
  })

  if (workflowQuery.isPending) {
    return <section className="rounded-2xl border border-line bg-white p-6 shadow-xs text-sm text-graphite">Загрузка статуса публикации...</section>
  }

  if (workflowQuery.isError) {
    return <section role="alert" className="rounded-2xl border border-red-200 bg-white p-6 text-sm text-red-700">Не удалось загрузить статус публикации: {errorMessage(workflowQuery.error, 'ошибка запроса')}</section>
  }

  const workflow = workflowQuery.data
  const busy = statusMutation.isPending || scheduleMutation.isPending || cancelMutation.isPending

  return (
    <section className="rounded-2xl border border-line bg-white p-6 shadow-xs" aria-label="Публикация страницы">
      <div className="flex flex-wrap items-center gap-3">
        <h3 className="text-base font-semibold text-ink">Публикация</h3>
        <Badge tone={statusTone(workflow.status)}>{statusLabels[workflow.status]}</Badge>
        {workflow.hasUnpublishedChanges && <Badge tone="warning">Есть неопубликованные изменения</Badge>}
      </div>

      <ScheduleSummary workflow={workflow} />

      <label className="mt-4 block text-sm font-medium text-ink">
        Комментарий к изменению статуса
        <input type="text" value={comment} onChange={(event) => setComment(event.target.value)} placeholder="Необязательно" className="mt-1 w-full rounded-lg border border-line-strong px-3 py-2" />
      </label>

      <div className="mt-3 flex flex-wrap gap-2">
        {workflow.transitions
          .filter((transition) => !handledByEditor.has(transition.status))
          .filter((transition) => !(workflow.status === 'scheduled' && transition.status === 'approved'))
          .map((transition) => (
            <TransitionButton
              key={transition.status}
              transition={transition}
              currentStatus={workflow.status}
              busy={busy}
              onClick={() => {
                if (transition.status === 'scheduled') {
                  setScheduleError(null)
                  setScheduleOpen(true)
                  return
                }
                statusMutation.mutate(transition.status)
              }}
            />
          ))}
        {workflow.canCancelSchedule && (
          <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => cancelMutation.mutate()}>
            {workflow.status === 'scheduled' ? 'Отменить публикацию по расписанию' : 'Отменить плановое снятие'}
          </Button>
        )}
      </div>
      {error && <p role="alert" className="mt-3 text-sm text-red-700">{error}</p>}

      <WorkflowHistory workflow={workflow} />

      <ScheduleDialog
        open={scheduleOpen}
        status={workflow.status}
        initialPublishAt={workflow.scheduledPublishAt}
        initialUnpublishAt={workflow.scheduledUnpublishAt}
        pending={scheduleMutation.isPending}
        error={scheduleError}
        onClose={() => setScheduleOpen(false)}
        onSubmit={(input) => scheduleMutation.mutate(input)}
      />
    </section>
  )
}

function TransitionButton({ transition, currentStatus, busy, onClick }: { transition: WorkflowTransition; currentStatus: WorkflowStatus; busy: boolean; onClick: () => void }) {
  const label = transition.status === 'scheduled' && currentStatus === 'published'
    ? 'Запланировать снятие…'
    : transition.status === 'scheduled' && currentStatus === 'scheduled'
      ? 'Изменить расписание…'
      : statusActionLabels[transition.status]

  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      disabled={busy || !transition.allowed}
      title={transition.allowed ? undefined : 'Недостаточно прав для этого перехода'}
      onClick={onClick}
    >
      {label}
    </Button>
  )
}

function ScheduleSummary({ workflow }: { workflow: PageWorkflow }) {
  if (workflow.scheduledPublishAt === null && workflow.scheduledUnpublishAt === null) return null

  return (
    <dl className="mt-3 grid gap-1 text-sm text-ink" data-testid="schedule-summary">
      {workflow.scheduledPublishAt !== null && (
        <div className="flex gap-2"><dt className="font-medium">Публикация по расписанию:</dt><dd>{formatDateTime(workflow.scheduledPublishAt)}</dd></div>
      )}
      {workflow.scheduledUnpublishAt !== null && (
        <div className="flex gap-2"><dt className="font-medium">Снятие по расписанию:</dt><dd>{formatDateTime(workflow.scheduledUnpublishAt)}</dd></div>
      )}
    </dl>
  )
}

function WorkflowHistory({ workflow }: { workflow: PageWorkflow }) {
  return (
    <div className="mt-6">
      <h4 className="text-sm font-semibold text-ink">Журнал событий</h4>
      {workflow.history.length === 0 && <p className="mt-2 text-sm text-graphite">Событий пока нет.</p>}
      <ul className="mt-2 space-y-2">
        {workflow.history.map((entry) => (
          <li key={entry.id} className="rounded-lg border border-line px-3 py-2 text-sm" data-testid="workflow-event">
            <p className="font-medium text-ink">
              {eventLabels[entry.event] ?? entry.event}
              {entry.fromStatus !== null && entry.toStatus !== null && entry.fromStatus !== entry.toStatus && (
                <span className="font-normal text-graphite"> · {statusLabels[entry.fromStatus as WorkflowStatus] ?? entry.fromStatus} → {statusLabels[entry.toStatus as WorkflowStatus] ?? entry.toStatus}</span>
              )}
            </p>
            <p className="text-xs text-graphite">{formatDateTime(entry.occurredAt)} · {entry.actor}{entry.comment ? ` · ${entry.comment}` : ''}</p>
          </li>
        ))}
      </ul>
    </div>
  )
}
