import { useSystemDeployQuery } from '../entities/system/api'
import { Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'
import { formatDateTime } from '../shared/lib/format'

export default function SystemDeployPage() {
  const deployQuery = useSystemDeployQuery()

  if (deployQuery.isPending) {
    return <PageLoadingState />
  }

  if (deployQuery.isError || deployQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить deploy-информацию"
        description="Проверьте endpoint /admin/api/system/deploy и права system.view."
      />
    )
  }

  return (
    <div>
      <PageHeader title="Деплой" description="Какая версия сайта сейчас развёрнута на сервере." />
      <Card title="Текущий релиз">
        <dl className="grid gap-2 text-sm sm:grid-cols-[180px_1fr]">
          <dt className="text-slate-500 dark:text-slate-400">Релиз</dt>
          <dd className="font-mono">{deployQuery.data.release ?? '—'}</dd>
          <dt className="text-slate-500 dark:text-slate-400">Коммит</dt>
          <dd className="break-all font-mono">{deployQuery.data.commit ?? '—'}</dd>
          <dt className="text-slate-500 dark:text-slate-400">Собран</dt>
          <dd>{formatDateTime(deployQuery.data.builtAt)}</dd>
          <dt className="text-slate-500 dark:text-slate-400">Развёрнут</dt>
          <dd>{formatDateTime(deployQuery.data.deployedAt)}</dd>
        </dl>
      </Card>
    </div>
  )
}
