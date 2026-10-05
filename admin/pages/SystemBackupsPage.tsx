import { useSystemBackupsQuery } from '../entities/system/api'
import { Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'
import { formatDateTime } from '../shared/lib/format'
import { formatBytes } from '../shared/lib/system-labels'

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
      <PageHeader title="Резервные копии" description="Резервные копии сайта и базы данных: когда сделана последняя и где лежат файлы." />
      <Card title="Последняя копия">
        {backupsQuery.data.latestBackup === null ? (
          <div className="text-sm">
            <p className="font-semibold text-red-700 dark:text-red-400">Резервных копий нет</p>
            <p className="mt-1 text-graphite dark:text-slate-400">
              В каталоге {backupsQuery.data.backupDirectory} нет файлов. При сбое восстановить сайт будет не из чего — настройте регулярное резервное копирование.
            </p>
          </div>
        ) : (
          <div className="text-sm">
            <p>{backupsQuery.data.latestBackup.name}</p>
            <p className="text-graphite dark:text-slate-400">
              {formatBytes(backupsQuery.data.latestBackup.size)} · {formatDateTime(backupsQuery.data.latestBackup.modifiedAt)}
            </p>
          </div>
        )}
      </Card>
    </div>
  )
}
