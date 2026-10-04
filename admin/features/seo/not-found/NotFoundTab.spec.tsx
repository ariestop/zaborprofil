import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test-utils'
import { NotFoundTab } from './NotFoundTab'

const apiRequest = vi.fn()

vi.mock('../../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const entries = [
  { id: 'E1', path: '/%D1%81%D1%82%D0%B0%D1%80%D0%B0%D1%8F/', hitCount: 42, firstSeenAt: '2026-10-01T10:00:00+00:00', lastSeenAt: '2026-10-03T10:00:00+00:00', referrer: 'https://www.google.com/search', hasRedirect: false },
  { id: 'E2', path: '/covered/', hitCount: 3, firstSeenAt: '2026-10-01T10:00:00+00:00', lastSeenAt: '2026-10-02T10:00:00+00:00', referrer: null, hasRedirect: true },
]

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string, options?: { method?: string }) => {
    if (url.startsWith('/admin/api/seo/not-found?') && options?.method === undefined) {
      return Promise.resolve({ items: entries, total: 2, totalHits: 45, page: 1, perPage: 25, pages: 1 })
    }
    if (options?.method === 'DELETE' && url.includes('olderThanDays')) {
      return Promise.resolve({ removed: 5 })
    }
    if (options?.method === 'DELETE') {
      return Promise.resolve({ removed: 2 })
    }
    if (options?.method === 'POST') {
      return Promise.resolve({ id: 'R1', sourcePath: '/x/', targetPath: '/y/', statusCode: 301, isActive: true, hitCount: 0, lastHitAt: null, updatedAt: '', warnings: [] })
    }
    return Promise.resolve({})
  })
})

afterEach(cleanup)

describe('NotFoundTab', () => {
  it('lists aggregated 404 paths with totals and marks covered paths', async () => {
    renderWithProviders(<NotFoundTab />)

    const rows = await screen.findAllByTestId('not-found-row')
    expect(rows).toHaveLength(2)
    expect(within(rows[0]).getByText('/старая/')).toBeTruthy()
    expect(within(rows[0]).getByText('42')).toBeTruthy()
    expect(within(rows[1]).getByText('Редирект есть')).toBeTruthy()
    expect((within(rows[1]).getByRole('button', { name: 'Создать редирект' }) as HTMLButtonElement).disabled).toBe(true)
    expect(await screen.findByText(/Адресов: 2 · обращений: 45/)).toBeTruthy()
  })

  it('opens the redirect form prefilled from a 404 entry and creates the redirect', async () => {
    renderWithProviders(<NotFoundTab />)
    const rows = await screen.findAllByTestId('not-found-row')

    fireEvent.click(within(rows[0]).getByRole('button', { name: 'Создать редирект' }))

    const source = await screen.findByLabelText('Старый URL') as HTMLInputElement
    expect(source.value).toBe('/старая/')
    fireEvent.change(screen.getByLabelText('Новый URL'), { target: { value: '/new/' } })
    fireEvent.click(screen.getByRole('button', { name: 'Сохранить' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/seo/redirects', {
        method: 'POST',
        body: { sourcePath: '/старая/', targetPath: '/new/', statusCode: 301, isActive: true },
      })
    })
  })

  it('clears stale entries only after confirmation', async () => {
    renderWithProviders(<NotFoundTab />)
    await screen.findAllByTestId('not-found-row')

    fireEvent.click(screen.getByRole('button', { name: 'Очистить старше 90 дней' }))
    expect(apiRequest.mock.calls.some(([, options]) => options?.method === 'DELETE')).toBe(false)
    fireEvent.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Очистить' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/seo/not-found?olderThanDays=90', { method: 'DELETE' })
    })
    expect(await screen.findByText('Удалено записей: 5.')).toBeTruthy()
  })

  it('deletes a single entry after confirmation', async () => {
    renderWithProviders(<NotFoundTab />)
    const rows = await screen.findAllByTestId('not-found-row')

    fireEvent.click(within(rows[0]).getByRole('button', { name: 'Удалить' }))
    fireEvent.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Удалить' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/seo/not-found/E1', { method: 'DELETE' })
    })
  })
})
