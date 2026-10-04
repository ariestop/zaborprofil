import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { makePageItem } from '../features/page-editor/fixtures'
import { renderWithProviders } from '../features/seo/test-utils'
import { loginAs } from '../shared/testing/roles'
import PagesPage from './PagesPage'

const apiRequest = vi.fn()

vi.mock('../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const pages = [
  makePageItem({ id: 'page-1', title: 'Забор из профнастила', path: '/zabor/' }),
  makePageItem({ id: 'page-2', title: 'Ворота', path: '/vorota/', isIndexable: false }),
]

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string, options?: { method?: string }) => {
    if (url === '/admin/api/content/pages') {
      return Promise.resolve({ pages })
    }
    if (url === '/admin/api/content/pages/bulk') {
      return Promise.resolve({ results: [{ id: 'page-1', ok: true, error: null }, { id: 'page-2', ok: true, error: null }], succeeded: 2, failed: 0 })
    }
    if (url === '/admin/api/content/pages/page-1/duplicate' && options?.method === 'POST') {
      return Promise.resolve(makePageItem({ id: 'page-3', title: 'Забор из профнастила (копия)', path: '/zabor-copy/' }))
    }
    return Promise.resolve({})
  })
})

afterEach(cleanup)

describe('PagesPage', () => {
  it('marks pages closed from indexing', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')

    const rows = await screen.findAllByTestId('page-row')
    expect(rows).toHaveLength(2)
    expect(within(rows[0] as HTMLElement).getByText('noindex')).toBeTruthy()
    expect(within(rows[1] as HTMLElement).queryByText('noindex')).toBeNull()
  })

  it('applies a bulk action to the selected pages', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')

    await screen.findAllByTestId('page-row')
    expect(screen.queryByRole('region', { name: 'Массовые действия' })).toBeNull()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Выбрать все страницы' }))
    expect((await screen.findByTestId('bulk-count')).textContent).toBe('Выбрано страниц: 2')

    fireEvent.click(screen.getByRole('button', { name: 'Закрыть от индексации' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/bulk', {
        method: 'POST',
        body: { ids: ['page-2', 'page-1'], action: 'indexable', indexable: false },
      })
    })
    await waitFor(() => expect(screen.queryByRole('region', { name: 'Массовые действия' })).toBeNull())
  })

  it('sends the chosen status for the selected pages only', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')

    await screen.findAllByTestId('page-row')
    fireEvent.click(screen.getByRole('checkbox', { name: 'Выбрать «Ворота»' }))
    const bulkBar = await screen.findByRole('region', { name: 'Массовые действия' })
    fireEvent.change(within(bulkBar).getByLabelText('Статус'), { target: { value: 'review' } })
    fireEvent.click(screen.getByRole('button', { name: 'Сменить статус' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/bulk', {
        method: 'POST',
        body: { ids: ['page-2'], action: 'status', status: 'review' },
      })
    })
  })

  it('duplicates a page', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')

    const rows = await screen.findAllByTestId('page-row')
    fireEvent.click(within(rows[1] as HTMLElement).getByRole('button', { name: 'Дублировать' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/duplicate', { method: 'POST', body: {} })
    })
    expect(await screen.findByText('Копия создана')).toBeTruthy()
  })

  it('hides duplication and bulk actions from a manager', async () => {
    loginAs('ROLE_MANAGER')
    renderWithProviders(<PagesPage />, '/admin/pages')

    await screen.findAllByTestId('page-row')
    expect(screen.queryByRole('button', { name: 'Дублировать' })).toBeNull()
    expect(screen.queryByRole('checkbox', { name: 'Выбрать все страницы' })).toBeNull()
  })

  it('lets an SEO specialist select pages but not duplicate them', async () => {
    loginAs('ROLE_SEO')
    renderWithProviders(<PagesPage />, '/admin/pages')

    await screen.findAllByTestId('page-row')
    expect(screen.queryByRole('button', { name: 'Дублировать' })).toBeNull()
    expect(screen.getByRole('checkbox', { name: 'Выбрать все страницы' })).toBeTruthy()
  })
})
