import { useDialog } from '../app/providers/dialog-provider'
import { useToast } from '../app/providers/toast-provider'
import { useCacheClearMutation, useSystemCacheQuery, useSystemSecurityQuery } from '../entities/system/api'
import { Button, Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'

export default function SystemCachePage() {
  const cacheQuery = useSystemCacheQuery()
  const securityQuery = useSystemSecurityQuery()
  const clearMutation = useCacheClearMutation()
  const { confirm } = useDialog()
  const { push } = useToast()

  if (cacheQuery.isPending) {
    return <PageLoadingState />
  }

  if (cacheQuery.isError || cacheQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить данные кэша"
        description="Проверьте endpoint /admin/api/system/cache и права system.view."
      />
    )
  }

  const canRunDangerousActions = securityQuery.data?.actor.roles.includes('ROLE_SUPER_ADMIN') === true

  async function runClearCache() {
    if (!canRunDangerousActions) {
      push({
        title: 'Недостаточно прав',
        description: 'Опасные действия доступны только ROLE_SUPER_ADMIN.',
      })
      return
    }

    if (!(await confirm({ title: 'Очистить кэш приложения?', description: 'Будет выполнен cache:clear. Первые запросы после очистки будут медленнее.', confirmLabel: 'Очистить' }))) {
      return
    }

    try {
      const result = await clearMutation.mutateAsync()
      push({
        title: result.exitCode === 0 ? 'Кэш очищен' : 'Ошибка очистки кэша',
        description: result.output || `Команда: ${result.command}`,
      })
    } catch {
      push({
        title: 'Ошибка очистки кэша',
        description: 'Операция завершилась с ошибкой. Проверьте audit log.',
      })
    }
  }

  return (
    <div>
      <PageHeader title="Кэш" description="Хранилище кэша и безопасная очистка." />
      <Card title="Хранилище кэша">
        <p className="text-sm">{cacheQuery.data.adapter}</p>
        <p className="text-sm text-graphite dark:text-slate-400">Пространство имён: {cacheQuery.data.namespace || 'не задано'}</p>
      </Card>
      <Card className="mt-4" title="Опасные действия">
        <Button variant="danger" onClick={() => void runClearCache()} disabled={clearMutation.isPending || !canRunDangerousActions}>
          Очистить кэш
        </Button>
      </Card>
    </div>
  )
}
