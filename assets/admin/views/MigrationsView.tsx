import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../api/client'
import type { MigrationItem } from '../types/api'

const pageSize = 15

export default function MigrationsView() {
  const [migrations, setMigrations] = useState<MigrationItem[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [pendingVersion, setPendingVersion] = useState<string | null>(null)
  const [currentPage, setCurrentPage] = useState(1)

  const sortedMigrations = useMemo(
    () => [...migrations].sort((left, right) => right.version.localeCompare(left.version)),
    [migrations],
  )
  const totalPages = Math.max(1, Math.ceil(sortedMigrations.length / pageSize))
  const pageStart = (currentPage - 1) * pageSize
  const pageEnd = Math.min(pageStart + pageSize, sortedMigrations.length)
  const paginatedMigrations = sortedMigrations.slice(pageStart, pageEnd)
  const pageNumbers = Array.from({ length: totalPages }, (_, index) => index + 1)

  const loadMigrations = async (): Promise<void> => {
    setError(null)
    const loaded = await apiRequest<MigrationItem[]>('/admin/api/settings/migrations')
    setMigrations(loaded)
    if (currentPage > Math.max(1, Math.ceil(loaded.length / pageSize))) {
      setCurrentPage(1)
    }
  }

  const refreshMigrations = async (): Promise<void> => {
    setIsLoading(true)
    try {
      await loadMigrations()
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Не удалось загрузить миграции.')
    } finally {
      setIsLoading(false)
    }
  }

  const runMigrationAction = async (migration: MigrationItem, action: 'apply' | 'rollback'): Promise<void> => {
    if (pendingVersion !== null) {
      return
    }

    const label = action === 'apply' ? 'применить' : 'откатить'
    if (!window.confirm(`Точно ${label} миграцию ${migration.file}?`)) {
      return
    }

    setPendingVersion(migration.version)
    setError(null)
    setNotice(null)

    try {
      await apiRequest(`/admin/api/settings/migrations/${encodeURIComponent(migration.version)}/${action}`, {
        method: 'POST',
      })
      setNotice(action === 'apply' ? 'Миграция применена.' : 'Миграция откатана.')
      await loadMigrations()
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Действие с миграцией не выполнено.')
    } finally {
      setPendingVersion(null)
    }
  }

  const formatDate = (value: string | null): string => {
    if (value === null) {
      return 'Не применялась'
    }

    return new Intl.DateTimeFormat('ru-RU', {
      dateStyle: 'short',
      timeStyle: 'short',
    }).format(new Date(value))
  }

  const goToPage = (page: number): void => {
    setCurrentPage(Math.min(Math.max(page, 1), totalPages))
  }

  useEffect(() => {
    void refreshMigrations()
  }, [])

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div className="flex items-center justify-between gap-4">
        <div>
          <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">Settings</p>
          <h2 className="mt-2 text-2xl font-bold text-slate-950">Миграции</h2>
          <p className="mt-2 text-sm text-slate-600">
            Список файлов из папки миграций, их описания и текущий статус применения. Применение выполняется по порядку до выбранной миграции.
          </p>
        </div>
        <button
          type="button"
          className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
          disabled={isLoading || pendingVersion !== null}
          onClick={() => { void refreshMigrations() }}
        >
          Обновить
        </button>
      </div>

      {isLoading && <p className="mt-6 text-slate-600">Загрузка...</p>}
      {!isLoading && error && <p className="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{error}</p>}
      {!isLoading && !error && notice && <p className="mt-6 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{notice}</p>}

      {!isLoading && !error && migrations.length === 0 && (
        <div className="mt-6 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
          Миграции не найдены.
        </div>
      )}

      {!isLoading && !error && migrations.length > 0 && (
        <div className="mt-6 overflow-hidden rounded-xl border border-slate-200">
          <div className="flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <span>
              Показаны {pageStart + 1}-{pageEnd} из {sortedMigrations.length}
            </span>
            <div className="flex items-center gap-2">
              <button
                type="button"
                className="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                disabled={currentPage === 1}
                onClick={() => goToPage(currentPage - 1)}
              >
                Назад
              </button>
              {pageNumbers.map((page) => (
                <button
                  key={page}
                  type="button"
                  className={[
                    'rounded-lg px-3 py-2 font-semibold',
                    currentPage === page ? 'bg-emerald-700 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                  ].join(' ')}
                  onClick={() => goToPage(page)}
                >
                  {page}
                </button>
              ))}
              <button
                type="button"
                className="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                disabled={currentPage === totalPages}
                onClick={() => goToPage(currentPage + 1)}
              >
                Вперёд
              </button>
            </div>
          </div>
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-left text-slate-600">
              <tr>
                <th className="px-4 py-3 font-semibold">Файл</th>
                <th className="px-4 py-3 font-semibold">Описание</th>
                <th className="px-4 py-3 font-semibold">Статус</th>
                <th className="px-4 py-3 font-semibold">Применена</th>
                <th className="px-4 py-3 text-right font-semibold">Действие</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {paginatedMigrations.map((migration) => (
                <tr key={migration.version}>
                  <td className="px-4 py-3 font-medium text-slate-950">{migration.file}</td>
                  <td className="max-w-xl px-4 py-3 text-slate-700">{migration.description}</td>
                  <td className="px-4 py-3">
                    <span
                      className={[
                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                        migration.isApplied ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700',
                      ].join(' ')}
                    >
                      {migration.isApplied ? 'Применена' : 'Не применена'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-500">{formatDate(migration.executedAt)}</td>
                  <td className="px-4 py-3 text-right">
                    {migration.isApplied ? (
                      <button
                        type="button"
                        className="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                        disabled={!migration.canRollback || pendingVersion !== null}
                        title={migration.canRollback ? 'Откатить миграцию' : 'Откатить можно только последнюю применённую миграцию'}
                        onClick={() => { void runMigrationAction(migration, 'rollback') }}
                      >
                        {pendingVersion === migration.version ? 'Откат...' : 'Откатить'}
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                        disabled={!migration.canApply || pendingVersion !== null}
                        onClick={() => { void runMigrationAction(migration, 'apply') }}
                      >
                        {pendingVersion === migration.version ? 'Применение...' : 'Применить'}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
