import { useToast } from '../app/providers/toast-provider'
import { useDialog } from '../app/providers/dialog-provider'
import { useProcessReloadMutation, useProcessRestartMutation, useSystemProcessesQuery, useSystemSecurityQuery } from '../entities/system/api'
import { Button, Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'

export default function SystemProcessesPage() {
  const processesQuery = useSystemProcessesQuery()
  const securityQuery = useSystemSecurityQuery()
  const restartMutation = useProcessRestartMutation()
  const reloadMutation = useProcessReloadMutation()
  const { confirm } = useDialog()
  const { push } = useToast()

  if (processesQuery.isPending) {
    return <PageLoadingState />
  }

  if (processesQuery.isError || processesQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить процессы"
        description="Проверьте endpoint /admin/api/system/processes и права system.view."
      />
    )
  }

  const canRunDangerousActions = securityQuery.data?.actor.roles.includes('ROLE_SUPER_ADMIN') === true

  async function runRestart(service: string) {
    if (!canRunDangerousActions) {
      push({
        title: 'Недостаточно прав',
        description: 'Опасные действия доступны только ROLE_SUPER_ADMIN.',
      })
      return
    }

    if (!(await confirm({ title: `Restart ${service}?`, description: 'Операция влияет на production-процессы.', confirmLabel: 'Restart' }))) {
      return
    }

    try {
      const result = await restartMutation.mutateAsync({ service })
      push({
        title: result.exitCode === 0 ? 'Сервис перезапущен' : 'Ошибка перезапуска',
        description: result.output || `Команда: ${result.command}`,
      })
    } catch {
      push({
        title: 'Ошибка перезапуска',
        description: 'Операция завершилась с ошибкой. Проверьте audit log.',
      })
    }
  }

  async function runReload(service: string) {
    if (!canRunDangerousActions) {
      push({
        title: 'Недостаточно прав',
        description: 'Опасные действия доступны только ROLE_SUPER_ADMIN.',
      })
      return
    }

    if (!(await confirm({ title: `Reload ${service}?`, description: 'Операция перечитает конфигурацию сервиса.', confirmLabel: 'Reload' }))) {
      return
    }

    try {
      const result = await reloadMutation.mutateAsync({ service })
      push({
        title: result.exitCode === 0 ? 'Сервис перезагружен' : 'Ошибка reload',
        description: result.output || `Команда: ${result.command}`,
      })
    } catch {
      push({
        title: 'Ошибка reload',
        description: 'Операция завершилась с ошибкой. Проверьте audit log.',
      })
    }
  }

  return (
    <div>
      <PageHeader title="Процессы и сервисы" description="Управление whitelisted системными сервисами." />
      <Card title="Поддерживаемые команды">
        <div className="space-y-3 text-sm">
          <p>Host: {processesQuery.data.host}</p>
          <div className="flex flex-wrap gap-2">
            {processesQuery.data.supportedActions.restart.map((service) => (
              <Button key={`restart-${service}`} variant="danger" onClick={() => void runRestart(service)} disabled={restartMutation.isPending || !canRunDangerousActions}>
                Restart {service}
              </Button>
            ))}
            {processesQuery.data.supportedActions.reload.map((service) => (
              <Button key={`reload-${service}`} onClick={() => void runReload(service)} disabled={reloadMutation.isPending || !canRunDangerousActions}>
                Reload {service}
              </Button>
            ))}
          </div>
        </div>
      </Card>
    </div>
  )
}
