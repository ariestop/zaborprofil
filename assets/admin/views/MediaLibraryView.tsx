import { useEffect, useState } from 'react'
import { apiRequest } from '../api/client'
import type { MediaAssetItem } from '../types/api'

function csrfHeaderName(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="admin-csrf-header"]')?.content ?? 'X-CSRF-Token'
}

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="admin-csrf-token"]')?.content ?? ''
}

export default function MediaLibraryView() {
  const [assets, setAssets] = useState<MediaAssetItem[]>([])
  const [error, setError] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)

  const loadAssets = async (): Promise<void> => {
    const response = await apiRequest<{ assets: MediaAssetItem[] }>('/admin/api/media/assets')
    setAssets(response.assets)
  }

  const uploadFile = async (event: React.ChangeEvent<HTMLInputElement>): Promise<void> => {
    const file = event.target.files?.[0]
    if (!file) {
      return
    }

    setUploading(true)
    setError(null)
    const body = new FormData()
    body.set('file', file)

    try {
      const response = await fetch('/admin/api/media/assets', {
        method: 'POST',
        headers: {
          [csrfHeaderName()]: csrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body,
      })

      if (!response.ok) {
        const payload = await response.json()
        throw new Error(String(payload.error ?? 'Не удалось загрузить файл'))
      }

      await loadAssets()
      event.target.value = ''
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось загрузить файл')
    } finally {
      setUploading(false)
    }
  }

  useEffect(() => {
    void loadAssets()
  }, [])

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-lg font-semibold text-slate-950">Media Library</h2>
      <p className="mt-1 text-sm text-slate-600">Безопасная загрузка изображений и PDF для контента и SEO.</p>

      <label className="mt-5 inline-flex cursor-pointer rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
        <input type="file" className="hidden" disabled={uploading} onChange={(event) => { void uploadFile(event) }} />
        {uploading ? 'Загрузка...' : 'Загрузить файл'}
      </label>
      {error && <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      <div className="mt-6 grid gap-3">
        {assets.map((asset) => (
          <article key={asset.id} className="rounded-xl border border-slate-200 p-4">
            <p className="font-medium text-slate-900">{asset.originalName}</p>
            <a href={asset.publicPath} target="_blank" rel="noreferrer" className="text-sm text-emerald-700">{asset.publicPath}</a>
            <p className="mt-1 text-xs text-slate-500">{asset.mimeType} · {asset.size} bytes · {asset.width ?? '-'}×{asset.height ?? '-'}</p>
            {asset.variants.length > 0 && (
              <div className="mt-3 flex flex-wrap gap-2">
                {asset.variants.map((variant) => (
                  <a
                    key={variant.publicPath}
                    href={variant.publicPath}
                    target="_blank"
                    rel="noreferrer"
                    className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200"
                  >
                    {variant.type} {variant.width}w
                  </a>
                ))}
              </div>
            )}
          </article>
        ))}
      </div>
    </section>
  )
}
