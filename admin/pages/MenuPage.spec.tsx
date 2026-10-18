import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { MenuItem } from '../entities/menu/api'
import { makePageItem } from '../features/page-editor/fixtures'
import { renderWithProviders } from '../features/seo/test-utils'
import MenuPage from './MenuPage'

const apiRequest = vi.fn()

vi.mock('../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

function item(id: string, label: string, sortOrder: number, parentId: string | null = null, extra: Partial<MenuItem> = {}): MenuItem {
  return { id, position: 'header', parentId, label, url: `/${id}/`, hint: null, sortOrder, isActive: true, updatedAt: '2026-10-14T10:00:00+00:00', ...extra }
}

const items: MenuItem[] = [
  item('fences', 'Заборы', 10),
  item('jaluzi', 'Забор Жалюзи', 10, 'fences', { hint: 'Металлические ламели' }),
  item('rancho', 'Забор Ранчо', 20, 'fences'),
  item('contacts', 'Контакты', 20),
  item('about', 'О компании', 10, null, { position: 'footer' }),
]

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string, options?: { method?: string, body?: Record<string, unknown> }) => {
    if (url === '/admin/api/menu/items' && options?.method === 'POST') {
      return Promise.resolve(item('new', (options.body?.label as string | undefined) ?? '', 30))
    }
    if (url === '/admin/api/menu/items') {
      return Promise.resolve({ items, positions: [{ value: 'header', label: 'Шапка сайта' }, { value: 'footer', label: 'Подвал' }, { value: 'service', label: 'Верхняя строка' }] })
    }
    if (url === '/admin/api/content/pages') {
      return Promise.resolve({ pages: [makePageItem({ id: 'p1', title: 'Забор Жалюзи в Саратове — купить', h1: 'Забор Жалюзи в Саратове', path: '/zabor-jaluzi/', status: 'published' })] })
    }
    return Promise.resolve(null)
  })
})

afterEach(cleanup)

const rows = () => screen.getAllByTestId('menu-row').map((row) => row.textContent ?? '')

function lastCallTo(url: string): { method?: string, body?: Record<string, unknown> } | undefined {
  const call = [...apiRequest.mock.calls].reverse().find(([callUrl]) => callUrl === url)
  return call?.[1] as { method?: string, body?: Record<string, unknown> } | undefined
}

describe('MenuPage', () => {
  it('shows the header menu as sections with children and the visitor preview', async () => {
    renderWithProviders(<MenuPage />, '/admin/menu')

    await screen.findAllByTestId('menu-row')
    expect(rows().map((text) => text.split('/')[0])).toEqual(['Заборы2 в разделе', 'Забор Жалюзи', 'Забор Ранчо', 'Контакты'])
    expect(screen.getByRole('tab', { name: /Шапка сайта\s*4/ }).getAttribute('aria-selected')).toBe('true')
    expect(within(screen.getByRole('region', { name: 'Предпросмотр' })).getByText('Металлические ламели')).toBeTruthy()

    fireEvent.click(screen.getByRole('tab', { name: /Подвал/ }))
    expect(rows()).toHaveLength(1)
    expect(rows()[0]).toContain('О компании')
  })

  it('moves an item with the arrow buttons and sends the whole order', async () => {
    renderWithProviders(<MenuPage />, '/admin/menu')
    await screen.findAllByTestId('menu-row')

    expect((screen.getByRole('button', { name: 'Выше: «Заборы»' }) as HTMLButtonElement).disabled).toBe(true)
    fireEvent.click(screen.getByRole('button', { name: 'Ниже: «Забор Жалюзи»' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/menu/positions/header/order', {
        method: 'PUT',
        body: { items: [
          { id: 'fences', parentId: null },
          { id: 'rancho', parentId: 'fences' },
          { id: 'jaluzi', parentId: 'fences' },
          { id: 'contacts', parentId: null },
        ] },
      })
    })
  })

  it('nests an item into a section from the row menu', async () => {
    renderWithProviders(<MenuPage />, '/admin/menu')
    await screen.findAllByTestId('menu-row')

    fireEvent.keyDown(screen.getByRole('button', { name: 'Действия с пунктом «Контакты»' }), { key: 'Enter' })
    fireEvent.keyDown(await screen.findByRole('menuitem', { name: 'Вложить в раздел…' }), { key: 'ArrowRight' })
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Заборы' }))

    await waitFor(() => {
      expect(lastCallTo('/admin/api/menu/positions/header/order')?.body).toEqual({ items: [
        { id: 'fences', parentId: null },
        { id: 'jaluzi', parentId: 'fences' },
        { id: 'rancho', parentId: 'fences' },
        { id: 'contacts', parentId: 'fences' },
      ] })
    })
  })

  it('adds an item from a site page and validates the fields first', async () => {
    renderWithProviders(<MenuPage />, '/admin/menu')
    await screen.findAllByTestId('menu-row')

    fireEvent.click(screen.getByRole('button', { name: 'Добавить пункт' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Добавить пункт' }))
    expect(await within(dialog).findByText('Укажите название')).toBeTruthy()
    expect(lastCallTo('/admin/api/menu/items')?.method).toBeUndefined()

    fireEvent.change(await within(dialog).findByLabelText('Страница сайта'), { target: { value: '/zabor-jaluzi/' } })
    expect((within(dialog).getByLabelText('Название') as HTMLInputElement).value).toBe('Забор Жалюзи в Саратове')
    fireEvent.change(within(dialog).getByLabelText('Название'), { target: { value: 'Забор Жалюзи' } })
    fireEvent.change(within(dialog).getByLabelText('Где показывать'), { target: { value: 'fences' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Добавить пункт' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/menu/items', {
        method: 'POST',
        body: { position: 'header', label: 'Забор Жалюзи', url: '/zabor-jaluzi/', hint: null, isActive: true, parentId: 'fences' },
      })
    })
    expect(await screen.findByText('Пункт добавлен')).toBeTruthy()
  })

  it('deletes a section only after confirming that its children go too', async () => {
    renderWithProviders(<MenuPage />, '/admin/menu')
    await screen.findAllByTestId('menu-row')

    fireEvent.keyDown(screen.getByRole('button', { name: 'Действия с пунктом «Заборы»' }), { key: 'Enter' })
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Удалить' }))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText(/удалятся подпункты \(2\)/)).toBeTruthy()
    expect(lastCallTo('/admin/api/menu/items/fences')).toBeUndefined()

    fireEvent.click(within(dialog).getByRole('button', { name: 'Удалить' }))
    await waitFor(() => expect(lastCallTo('/admin/api/menu/items/fences')).toEqual({ method: 'DELETE' }))
  })

  it('moves an item to another menu', async () => {
    renderWithProviders(<MenuPage />, '/admin/menu')
    await screen.findAllByTestId('menu-row')

    fireEvent.keyDown(screen.getByRole('button', { name: 'Действия с пунктом «Контакты»' }), { key: 'Enter' })
    fireEvent.keyDown(await screen.findByRole('menuitem', { name: 'Перенести в другое меню…' }), { key: 'ArrowRight' })
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Подвал' }))

    await waitFor(() => {
      expect(lastCallTo('/admin/api/menu/items/contacts')).toEqual({
        method: 'PUT',
        body: { position: 'footer', label: 'Контакты', url: '/contacts/', hint: null, isActive: true, parentId: null },
      })
    })
  })
})
