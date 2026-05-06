import { useEffect, useState } from 'react'
import { apiRequest } from '../api/client'
import type { MaintenanceStatus } from '../types/api'

export default function MaintenanceView() {
  const [status, setStatus] = useState<MaintenanceStatus | null>(null)
  const [message, setMessage] = useState('Сайт временно находится на техническом обслуживании.')
  const [allowedIps, setAllowedIps] = useState('')
  const [error, setError] = useState<string | null>(null)

  const loadStatus = async (): Promise<void> => {
    try {
      const loaded = await apiRequest<MaintenanceStatus>('/admin/api/system/maintenance')
      setStatus(loaded)
      if (loaded.message) {
        setMessage(loaded.message)
      }
      setAllowedIps((loaded.allowedIps ?? []).join('\n'))
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Не удалось загрузить maintenance status.')
    }
  }

  const enableMaintenance = async (): Promise<void> => {
    const response = await apiRequest<MaintenanceStatus>('/admin/api/system/maintenance/on', {
      method: 'POST',
      body: {
        message,
        allowedIps: allowedIps.split('\n').map((item) => item.trim()).filter(Boolean),
      },
    })
    setStatus(response)
  }

  const disableMaintenance = async (): Promise<void> => {
    const response = await apiRequest<MaintenanceStatus>('/admin/api/system/maintenance/off', {
      method: 'POST',
      body: {},
    })
    setStatus(response)
  }

  useEffect(() => {
    void loadStatus()
  }, [])

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">System</p>
      <h2 className="mt-2 text-2xl font-bold text-slate-950">Maintenance Mode</h2>

      {error && <p className="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{error}</p>}

      {status && (
        <div className="mt-6 rounded-xl bg-slate-50 p-4">
          <p className="text-sm text-slate-500">Текущий статус</p>
          <p className={['mt-1 text-lg font-bold', status.enabled ? 'text-red-700' : 'text-emerald-700'].join(' ')}>
            {status.enabled ? 'Включен' : 'Выключен'}
          </p>
          {status.enabledAt && <p className="mt-1 text-sm text-slate-500">С {status.enabledAt}</p>}
        </div>
      )}

      <label className="mt-6 block text-sm font-medium text-slate-700">
        Сообщение
        <textarea value={message} className="mt-2 min-h-28 w-full rounded-lg border border-slate-300 p-3" onChange={(event) => setMessage(event.target.value)} />
      </label>

      <label className="mt-4 block text-sm font-medium text-slate-700">
        Whitelist IP, по одному на строку
        <textarea value={allowedIps} className="mt-2 min-h-24 w-full rounded-lg border border-slate-300 p-3" onChange={(event) => setAllowedIps(event.target.value)} />
      </label>

      <div className="mt-6 flex gap-3">
        <button className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800" type="button" onClick={() => { void enableMaintenance() }}>
          Включить maintenance
        </button>
        <button className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" type="button" onClick={() => { void disableMaintenance() }}>
          Выключить
        </button>
      </div>
    </section>
  )
}
