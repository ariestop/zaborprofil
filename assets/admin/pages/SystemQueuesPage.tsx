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

    if (!(await confirm({ title: 'Retry failed messages?', description: 'Повторная обработка всех failed-сообщений.', confirmLabel: 'Retry failed' }))) {
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

    if (!(await confirm({ title: 'Remove failed messages?', description: 'Операция необратима для failed очереди.', confirmLabel: 'Remove failed' }))) {
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
      <PageHeader title="Очереди Symfony Messenger" description="Мониторинг async/failed transport и ручные действия." />
      <Card title="Состояние очередей">
        <div className="space-y-2 text-sm">
          <p>async: {queuesQuery.data.transports.async}</p>
          <p>failed: {queuesQuery.data.transports.failed}</p>
        </div>
      </Card>
      <Card className="mt-4" title="Danger zone">
        <div className="flex gap-2">
          <Button variant="danger" onClick={() => void runRetryFailed()} disabled={retryFailedMutation.isPending || !canRunDangerousActions}>Retry failed</Button>
          <Button variant="danger" onClick={() => void runRemoveFailed()} disabled={removeFailedMutation.isPending || !canRunDangerousActions}>Remove failed</Button>
        </div>
      </Card>
    </div>
  )
}
