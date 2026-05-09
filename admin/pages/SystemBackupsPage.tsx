import { useSystemBackupsQuery } from '../entities/system/api'
import { Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'

export default function SystemBackupsPage() {
  const backupsQuery = useSystemBackupsQuery()

  if (backupsQuery.isPending) {
    return <PageLoadingState />
  }

  if (backupsQuery.isError || backupsQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить состояние бэкапов"
        description="Проверьте endpoint /admin/api/system/backups и права system.view."
      />
    )
  }

  return (
    <div>
      <PageHeader title="Бэкапы" description="Мониторинг каталога резервных копий и последнего snapshot." />
      <Card title="Последний бэкап">
        {backupsQuery.data.latestBackup === null ? (
          <p className="text-sm text-slate-500 dark:text-slate-400">Файлы бэкапов не обнаружены.</p>
        ) : (
          <div className="text-sm">
            <p>{backupsQuery.data.latestBackup.name}</p>
            <p className="text-slate-500 dark:text-slate-400">
              {backupsQuery.data.latestBackup.size} bytes, {backupsQuery.data.latestBackup.modifiedAt}
            </p>
          </div>
        )}
      </Card>
    </div>
  )
}
