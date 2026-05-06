import { useEffect, useState } from 'react'
import { apiRequest } from '../api/client'
import type { MenuItem } from '../types/api'

interface MenuPositionOption {
  value: string
  label: string
}

export default function MenuView() {
  const [items, setItems] = useState<MenuItem[]>([])
  const [positions, setPositions] = useState<MenuPositionOption[]>([
    { value: 'header', label: 'Header' },
    { value: 'footer', label: 'Footer' },
    { value: 'service', label: 'Service navigation' },
  ])
  const [error, setError] = useState<string | null>(null)
  const [form, setForm] = useState({
    position: 'header',
    label: '',
    url: '/',
    sortOrder: 0,
    isActive: true,
  })

  const loadItems = async (): Promise<void> => {
    const response = await apiRequest<{ items: MenuItem[], positions?: MenuPositionOption[] }>('/admin/api/menu/items')
    setItems(response.items)
    setPositions(response.positions ?? positions)
  }

  const createItem = async (): Promise<void> => {
    setError(null)
    try {
      await apiRequest<MenuItem>('/admin/api/menu/items', {
        method: 'POST',
        body: { ...form },
      })
      setForm((state) => ({ ...state, label: '', url: '/' }))
      await loadItems()
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось сохранить пункт меню')
    }
  }

  const updateItem = async (item: MenuItem): Promise<void> => {
    setError(null)
    try {
      await apiRequest<MenuItem>(`/admin/api/menu/items/${item.id}`, {
        method: 'PUT',
        body: {
          position: item.position,
          label: item.label,
          url: item.url,
          sortOrder: item.sortOrder,
          isActive: item.isActive,
        },
      })
      await loadItems()
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось обновить пункт меню')
    }
  }

  const deleteItem = async (item: MenuItem): Promise<void> => {
    setError(null)
    try {
      await apiRequest<void>(`/admin/api/menu/items/${item.id}`, { method: 'DELETE' })
      await loadItems()
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось удалить пункт меню')
    }
  }

  useEffect(() => {
    void loadItems()
  }, [])

  return (
    <section className="space-y-6">
      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-950">Меню сайта</h2>
        <p className="mt-1 text-sm text-slate-600">Header, footer и service navigation кэшируются и доступны в Twig через `menu_items(position)`.</p>

        <form
          className="mt-5 grid grid-cols-[140px_1fr_1fr_100px_auto] gap-3"
          onSubmit={(event) => {
            event.preventDefault()
            void createItem()
          }}
        >
          <select value={form.position} className="rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((state) => ({ ...state, position: event.target.value }))}>
            {positions.map((position) => (
              <option key={position.value} value={position.value}>{position.label}</option>
            ))}
          </select>
          <input value={form.label} className="rounded-lg border border-slate-300 px-3 py-2" placeholder="Название" onChange={(event) => setForm((state) => ({ ...state, label: event.target.value }))} />
          <input value={form.url} className="rounded-lg border border-slate-300 px-3 py-2" placeholder="/url/" onChange={(event) => setForm((state) => ({ ...state, url: event.target.value }))} />
          <input value={form.sortOrder} type="number" className="rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((state) => ({ ...state, sortOrder: Number(event.target.value) }))} />
          <button className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Добавить</button>
        </form>
        {error && <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        {items.map((item) => (
          <article key={item.id} className="grid grid-cols-[140px_1fr_1fr_100px_90px_auto] items-center gap-3 border-b border-slate-100 py-3 last:border-0">
            <select value={item.position} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" onChange={(event) => setItems((prev) => prev.map((current) => (current.id === item.id ? { ...current, position: event.target.value } : current)))}>
              {positions.map((position) => (
                <option key={position.value} value={position.value}>{position.label}</option>
              ))}
            </select>
            <input value={item.label} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" onChange={(event) => setItems((prev) => prev.map((current) => (current.id === item.id ? { ...current, label: event.target.value } : current)))} />
            <input value={item.url} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" onChange={(event) => setItems((prev) => prev.map((current) => (current.id === item.id ? { ...current, url: event.target.value } : current)))} />
            <input value={item.sortOrder} type="number" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" onChange={(event) => setItems((prev) => prev.map((current) => (current.id === item.id ? { ...current, sortOrder: Number(event.target.value) } : current)))} />
            <label className="inline-flex items-center gap-2 text-sm text-slate-600">
              <input checked={item.isActive} type="checkbox" className="rounded border-slate-300" onChange={(event) => setItems((prev) => prev.map((current) => (current.id === item.id ? { ...current, isActive: event.target.checked } : current)))} />
              active
            </label>
            <div className="flex justify-end gap-2">
              <button className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700" onClick={() => { void updateItem(item) }} type="button">
                Save
              </button>
              <button className="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100" onClick={() => { void deleteItem(item) }} type="button">
                Delete
              </button>
            </div>
          </article>
        ))}
      </div>
    </section>
  )
}
