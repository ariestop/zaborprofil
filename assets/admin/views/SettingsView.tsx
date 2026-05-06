import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { apiRequest } from '../api/client'
import type { SettingItem } from '../types/api'

export default function SettingsView() {
  const navigate = useNavigate()
  const [settings, setSettings] = useState<SettingItem[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const load = async (): Promise<void> => {
      try {
        setSettings(await apiRequest<SettingItem[]>('/admin/api/settings'))
      } catch (exception) {
        setError(exception instanceof Error ? exception.message : 'Не удалось загрузить настройки.')
      } finally {
        setIsLoading(false)
      }
    }

    void load()
  }, [])

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div className="flex items-center justify-between gap-4">
        <div>
          <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">Settings</p>
          <h2 className="mt-2 text-2xl font-bold text-slate-950">Настройки проекта</h2>
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            onClick={() => navigate('/admin/settings/migrations')}
          >
            Миграции
          </button>
          <button className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            Добавить настройку
          </button>
        </div>
      </div>

      {isLoading && <p className="mt-6 text-slate-600">Загрузка...</p>}
      {!isLoading && error && <p className="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{error}</p>}
      {!isLoading && !error && settings.length === 0 && (
        <div className="mt-6 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
          Настройки пока не созданы.
        </div>
      )}
      {!isLoading && !error && settings.length > 0 && (
        <div className="mt-6 overflow-hidden rounded-xl border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-left text-slate-600">
              <tr>
                <th className="px-4 py-3 font-semibold">Scope</th>
                <th className="px-4 py-3 font-semibold">Key</th>
                <th className="px-4 py-3 font-semibold">Value</th>
                <th className="px-4 py-3 font-semibold">Updated</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {settings.map((setting) => (
                <tr key={setting.id}>
                  <td className="px-4 py-3 font-medium text-slate-950">{setting.scope}</td>
                  <td className="px-4 py-3 text-slate-700">{setting.key}</td>
                  <td className="px-4 py-3 text-slate-600">{JSON.stringify(setting.value)}</td>
                  <td className="px-4 py-3 text-slate-500">{setting.updatedAt}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
