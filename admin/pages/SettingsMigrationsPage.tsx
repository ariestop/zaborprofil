import { createColumnHelper } from '@tanstack/react-table'
import { useDialog } from '../app/providers/dialog-provider'
import { useToast } from '../app/providers/toast-provider'
import { useApplyMigrationMutation, useRollbackMigrationMutation, useSettingsMigrationsQuery } from '../entities/settings/api'
import { ApiError } from '../shared/api/client'
import { Badge, Button, Card, DataTable, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'
import type { MigrationItem } from '../types/api'

export default function SettingsMigrationsPage() {
  const migrationsQuery = useSettingsMigrationsQuery()
  const applyMigration = useApplyMigrationMutation()
  const rollbackMigration = useRollbackMigrationMutation()
  const { confirm } = useDialog()
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
                const confirmed = await confirm({
                  title: 'Применить миграцию?',
                  description: `Версия ${migration.version} изменит схему БД. Действие попадёт в журнал аудита.`,
                  confirmLabel: 'Применить',
                })
                if (!confirmed) {
                  return
                }

                try {
                  const result = await applyMigration.mutateAsync(migration.version)
                  push({
                    title: 'Миграция применена',
                    description: `Выполнено шагов: ${result.executedCount}.`,
                  })
                } catch (error) {
                  push({
                    title: 'Ошибка применения миграции',
                    description: error instanceof ApiError ? error.message : `Не удалось применить миграцию ${migration.version}.`,
                  })
                }
              }}
            >
              Применить
            </Button>
            <Button
              type="button"
              size="sm"
              variant="danger"
              disabled={!migration.canRollback || isBusy}
              onClick={async () => {
                const confirmed = await confirm({
                  title: 'Откатить миграцию?',
                  description: `Откат ${migration.version} может удалить данные. Действие попадёт в журнал аудита.`,
                  confirmLabel: 'Откатить',
                })
                if (!confirmed) {
                  return
                }

                try {
                  const result = await rollbackMigration.mutateAsync(migration.version)
                  push({
                    title: 'Миграция откатана',
                    description: `Выполнено шагов: ${result.executedCount}.`,
                  })
                } catch (error) {
                  push({
                    title: 'Ошибка отката миграции',
                    description: error instanceof ApiError ? error.message : `Не удалось откатить миграцию ${migration.version}.`,
                  })
                }
              }}
            >
              Откатить
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

  const actionsAllowed = migrationsQuery.data.every((migration) => migration.actionsAllowed)

  return (
    <div>
      <PageHeader title="Миграции" description="Изменения структуры базы данных: какие применены и какие ждут применения." />
      {actionsAllowed ? null : (
        <p className="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
          Запуск миграций из веб-интерфейса недоступен: он отключён на этом окружении или нужна роль ROLE_SUPER_ADMIN. Выполняйте миграции из CLI (doctrine:migrations:migrate).
        </p>
      )}
      <Card title="Список миграций">
        <DataTable columns={columns} data={migrationsQuery.data} />
      </Card>
    </div>
  )
}
