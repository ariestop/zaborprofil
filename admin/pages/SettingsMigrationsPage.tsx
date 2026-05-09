import { createColumnHelper } from '@tanstack/react-table'
import { useToast } from '../app/providers/toast-provider'
import { useApplyMigrationMutation, useRollbackMigrationMutation, useSettingsMigrationsQuery } from '../entities/settings/api'
import { Badge, Button, Card, DataTable, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'
import type { MigrationItem } from '../types/api'

export default function SettingsMigrationsPage() {
  const migrationsQuery = useSettingsMigrationsQuery()
  const applyMigration = useApplyMigrationMutation()
  const rollbackMigration = useRollbackMigrationMutation()
  const { push } = useToast()
  const columnHelper = createColumnHelper<MigrationItem>()

  const columns = [
    columnHelper.accessor('version', { header: 'Версия' }),
    columnHelper.accessor('file', { header: 'Файл' }),
    columnHelper.accessor('description', { header: 'Описание' }),
    columnHelper.accessor('isApplied', {
      header: 'Статус',
      cell: ({ getValue }) => (
        <Badge tone={getValue() ? 'success' : 'warning'}>
          {getValue() ? 'Применена' : 'Не применена'}
        </Badge>
      ),
    }),
    columnHelper.display({
      id: 'actions',
      header: 'Действия',
      cell: ({ row }) => {
        const migration = row.original
        const isBusy = applyMigration.isPending || rollbackMigration.isPending

        return (
          <div className="flex gap-2">
            <Button
              type="button"
              size="sm"
              disabled={!migration.canApply || isBusy}
              onClick={async () => {
                try {
                  const result = await applyMigration.mutateAsync(migration.version)
                  push({
                    title: 'Миграция применена',
                    description: `Выполнено шагов: ${result.executedCount}.`,
                  })
                } catch {
                  push({
                    title: 'Ошибка применения миграции',
                    description: `Не удалось применить миграцию ${migration.version}.`,
                  })
                }
              }}
            >
              Apply
            </Button>
            <Button
              type="button"
              size="sm"
              variant="danger"
              disabled={!migration.canRollback || isBusy}
              onClick={async () => {
                try {
                  const result = await rollbackMigration.mutateAsync(migration.version)
                  push({
                    title: 'Миграция откатана',
                    description: `Выполнено шагов: ${result.executedCount}.`,
                  })
                } catch {
                  push({
                    title: 'Ошибка отката миграции',
                    description: `Не удалось откатить миграцию ${migration.version}.`,
                  })
                }
              }}
            >
              Rollback
            </Button>
          </div>
        )
      },
    }),
  ]

  if (migrationsQuery.isPending) {
    return <PageLoadingState />
  }

  if (migrationsQuery.isError || migrationsQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить миграции"
        description="Проверьте endpoint /admin/api/settings/migrations и права settings.edit."
      />
    )
  }

  return (
    <div>
      <PageHeader title="Миграции" description="Управление Doctrine-миграциями из админки." />
      <Card title="Список миграций">
        <DataTable columns={columns} data={migrationsQuery.data} />
      </Card>
    </div>
  )
}
