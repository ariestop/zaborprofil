import { useEffect, useState } from 'react'
import { apiRequest } from '../api/client'
import type { SystemHealthResponse } from '../types/api'

export default function SystemHealthView() {
  const [health, setHealth] = useState<SystemHealthResponse | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const loadHealth = async (): Promise<void> => {
    setIsLoading(true)
    setError(null)

    try {
      setHealth(await apiRequest<SystemHealthResponse>('/admin/api/system/health'))
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Не удалось загрузить health status.')
    } finally {
      setIsLoading(false)
    }
  }

  const statusClass = (status: string): string => {
    if (status === 'ok') {
      return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
    }
    if (status === 'warning') {
      return 'bg-amber-50 text-amber-700 ring-amber-200'
    }
    return 'bg-red-50 text-red-700 ring-red-200'
  }

  useEffect(() => {
    void loadHealth()
  }, [])

  return (
    <section className="space-y-6">
      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex items-start justify-between gap-4">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">System</p>
            <h2 className="mt-2 text-2xl font-bold text-slate-950">Health Center</h2>
            <p className="mt-2 text-sm text-slate-600">
              Проверка приложения, БД, Redis/cache, storage, миграций и диска.
            </p>
          </div>
          <button
            type="button"
            className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            onClick={() => { void loadHealth() }}
          >
            Обновить
          </button>
        </div>

        {isLoading && <p className="mt-6 text-slate-600">Загрузка...</p>}
        {!isLoading && error && <p className="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{error}</p>}

        {!isLoading && health && (
          <div className="mt-6 grid gap-4 md:grid-cols-4">
            <div className="rounded-xl bg-slate-50 p-4">
              <p className="text-xs uppercase tracking-wide text-slate-500">Status</p>
              <p className={['mt-1 text-lg font-bold', health.status === 'ok' ? 'text-emerald-700' : 'text-red-700'].join(' ')}>
                {health.status}
              </p>
            </div>
            <div className="rounded-xl bg-slate-50 p-4">
              <p className="text-xs uppercase tracking-wide text-slate-500">APP_ENV</p>
              <p className="mt-1 text-lg font-bold text-slate-950">{health.environment.appEnv}</p>
            </div>
            <div className="rounded-xl bg-slate-50 p-4">
              <p className="text-xs uppercase tracking-wide text-slate-500">APP_DEBUG</p>
              <p className="mt-1 text-lg font-bold text-slate-950">{health.environment.appDebug ? 'true' : 'false'}</p>
            </div>
            <div className="rounded-xl bg-slate-50 p-4">
              <p className="text-xs uppercase tracking-wide text-slate-500">PHP</p>
              <p className="mt-1 text-lg font-bold text-slate-950">{health.environment.phpVersion}</p>
            </div>
          </div>
        )}
      </div>

      {!!health?.warnings.length && (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6">
          <h3 className="text-base font-semibold text-amber-900">System Warnings</h3>
          <ul className="mt-3 space-y-2 text-sm text-amber-800">
            {health.warnings.map((warning) => (
              <li key={warning.code}>{warning.message}</li>
            ))}
          </ul>
        </div>
      )}

      {health && (
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-left text-slate-600">
              <tr>
                <th className="px-4 py-3 font-semibold">Check</th>
                <th className="px-4 py-3 font-semibold">Status</th>
                <th className="px-4 py-3 font-semibold">Message</th>
                <th className="px-4 py-3 font-semibold">Details</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {health.checks.map((check) => (
                <tr key={check.name}>
                  <td className="px-4 py-3 font-medium text-slate-950">{check.label}</td>
                  <td className="px-4 py-3">
                    <span className={['rounded-full px-2 py-1 text-xs font-semibold ring-1', statusClass(check.status)].join(' ')}>
                      {check.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-700">{check.message}</td>
                  <td className="px-4 py-3 text-slate-500">{JSON.stringify(check.details)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
