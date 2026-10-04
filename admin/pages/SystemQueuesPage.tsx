import { useDialog } from '../app/providers/dialog-provider'
import { useToast } from '../app/providers/toast-provider'
import { useQueueRemoveFailedMutation, useQueueRetryFailedMutation, useSystemQueuesQuery, useSystemSecurityQuery } from '../entities/system/api'
import { Button, Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'

export default function SystemQueuesPage() {
  const queuesQuery = useSystemQueuesQuery()
  const securityQuery = useSystemSecurityQuery()
  const retryFailedMutation = useQueueRetryFailedMutation()
  const removeFailedMutation = useQueueRemoveFailedMutation()
  const { confirm } = useDialog()
  const { push } = useToast()

  if (queuesQuery.isPending) {
    return <PageLoadingState />
  }

  if (queuesQuery.isError || queuesQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить очереди"
        description="Проверьте endpoint /admin/api/system/queues и права system.view."
      />
    )
  }

  const canRunDangerousActions = securityQuery.data?.actor.roles.includes('ROLE_SUPER_ADMIN') === true

  async function runRetryFailed() {
    if (!canRunDangerousActions) {
      push({
        title: 'Недостаточно прав',
        description: 'Опасные действия доступны только ROLE_SUPER_ADMIN.',
      })
      return
    }

    if (!(await confirm({ title: 'Повторить задачи с ошибкой?', description: 'Все задачи из очереди ошибок будут отправлены на повторную обработку.', confirmLabel: 'Повторить' }))) {
      return
    }

    try {
      const result = await retryFailedMutation.mutateAsync()
      push({
        title: result.exitCode === 0 ? 'Повтор выполнен' : 'Ошибка retry',
        description: result.output || `Команда: ${result.command}`,
      })
    } catch {
      push({
        title: 'Ошибка retry',
        description: 'Операция завершилась с ошибкой. Проверьте audit log.',
      })
    }
  }

  async function runRemoveFailed() {
    if (!canRunDangerousActions) {
      push({
        title: 'Недостаточно прав',
        description: 'Опасные действия доступны только ROLE_SUPER_ADMIN.',
      })
      return
    }

    if (!(await confirm({ title: 'Удалить задачи с ошибкой?', description: 'Задачи из очереди ошибок будут удалены без возможности восстановления.', confirmLabel: 'Удалить' }))) {
      return
    }

    try {
      const result = await removeFailedMutation.mutateAsync()
      push({
        title: result.exitCode === 0 ? 'Очередь очищена' : 'Ошибка удаления',
        description: result.output || `Команда: ${result.command}`,
      })
    } catch {
      push({
        title: 'Ошибка удаления',
        description: 'Операция завершилась с ошибкой. Проверьте audit log.',
      })
    }
  }

  return (
    <div>
      <PageHeader title="Очереди задач" description="Фоновые задачи (уведомления, письма): сколько ждут обработки и сколько завершились ошибкой." />
      <Card title="Состояние очередей">
        <div className="space-y-2 text-sm">
          <p>Ожидают обработки: {queuesQuery.data.transports.async}</p>
          <p>С ошибкой: {queuesQuery.data.transports.failed}</p>
        </div>
      </Card>
      <Card className="mt-4" title="Опасные действия">
        <div className="flex gap-2">
          <Button variant="danger" onClick={() => void runRetryFailed()} disabled={retryFailedMutation.isPending || !canRunDangerousActions}>Повторить задачи с ошибкой</Button>
          <Button variant="danger" onClick={() => void runRemoveFailed()} disabled={removeFailedMutation.isPending || !canRunDangerousActions}>Удалить задачи с ошибкой</Button>
        </div>
      </Card>
    </div>
  )
}
