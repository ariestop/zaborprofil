import { useEffect, useState } from 'react'
import { apiRequest } from '../api/client'
import type { RedirectItem } from '../types/api'

export default function RedirectsView() {
  const [redirects, setRedirects] = useState<RedirectItem[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const load = async (): Promise<void> => {
      try {
        setRedirects(await apiRequest<RedirectItem[]>('/admin/api/seo/redirects'))
      } catch (exception) {
        setError(exception instanceof Error ? exception.message : 'Не удалось загрузить редиректы.')
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
          <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">SEO</p>
          <h2 className="mt-2 text-2xl font-bold text-slate-950">Редиректы</h2>
        </div>
        <button className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
          Добавить редирект
        </button>
      </div>

      {isLoading && <p className="mt-6 text-slate-600">Загрузка...</p>}
      {!isLoading && error && <p className="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{error}</p>}
      {!isLoading && !error && redirects.length === 0 && (
        <div className="mt-6 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
          Редиректы пока не созданы.
        </div>
      )}
      {!isLoading && !error && redirects.length > 0 && (
        <div className="mt-6 overflow-hidden rounded-xl border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-left text-slate-600">
              <tr>
                <th className="px-4 py-3 font-semibold">Source</th>
                <th className="px-4 py-3 font-semibold">Target</th>
                <th className="px-4 py-3 font-semibold">Status</th>
                <th className="px-4 py-3 font-semibold">Hits</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {redirects.map((redirect) => (
                <tr key={redirect.id}>
                  <td className="px-4 py-3 font-medium text-slate-950">{redirect.sourcePath}</td>
                  <td className="px-4 py-3 text-slate-700">{redirect.targetPath}</td>
                  <td className="px-4 py-3 text-slate-600">{redirect.statusCode}</td>
                  <td className="px-4 py-3 text-slate-500">{redirect.hitCount}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
