import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../../shared/api/client'
import { renderWithProviders } from '../test-utils'
import { RedirectsTab } from './RedirectsTab'

const apiRequest = vi.fn()

vi.mock('../../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const redirect = {
  id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
  sourcePath: '/%D1%81%D1%82%D0%B0%D1%80%D0%B0%D1%8F/',
  targetPath: '/new-page/',
  statusCode: 301,
  isActive: true,
  hitCount: 1234,
  lastHitAt: null,
  updatedAt: '2026-10-01T10:00:00+00:00',
}

function listResponse(items = [redirect]) {
  return {
    items,
    total: items.length,
    page: 1,
    perPage: 25,
    pages: 1,
    counts: { total: items.length, active: items.length, inactive: 0 },
  }
}

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string, options?: { method?: string }) => {
    if (url.startsWith('/admin/api/seo/redirects?')) {
      return Promise.resolve(listResponse())
    }
    if (options?.method === 'POST' && url === '/admin/api/seo/redirects') {
      return Promise.resolve({ ...redirect, id: 'NEW', warnings: [{ code: 'chain', message: 'Цепочка редиректов: /a/ → /b/ → /c/.' }] })
    }
    if (options?.method === 'DELETE') {
      return Promise.resolve(undefined)
    }
    return Promise.resolve({})
  })
})

afterEach(cleanup)

describe('RedirectsTab', () => {
  it('lists redirects with decoded urls, hit counts and summary', async () => {
    renderWithProviders(<RedirectsTab />)

    const row = await screen.findByTestId('redirect-row')
    expect(within(row).getByText('/старая/')).toBeTruthy()
    expect(within(row).getByText('/new-page/')).toBeTruthy()
    expect(within(row).getByText('301')).toBeTruthy()
    expect(within(row).getByText(/1.?234/)).toBeTruthy()
    expect(await screen.findByText(/Всего: 1 · активных: 1 · отключённых: 0/)).toBeTruthy()
  })

  it('sends the debounced search term to the API', async () => {
    renderWithProviders(<RedirectsTab />)
    await screen.findByTestId('redirect-row')

    fireEvent.change(screen.getByLabelText('Поиск редиректов'), { target: { value: 'fence' } })

    await waitFor(() => {
      expect(apiRequest.mock.calls.some(([url]) => typeof url === 'string' && url.includes('q=fence'))).toBe(true)
    })
  })

  it('validates the form on the client before calling the API', async () => {
    renderWithProviders(<RedirectsTab />)
    await screen.findByTestId('redirect-row')

    fireEvent.click(screen.getByRole('button', { name: 'Добавить редирект' }))
    fireEvent.change(screen.getByLabelText('Старый URL'), { target: { value: '/same/' } })
    fireEvent.change(screen.getByLabelText('Новый URL'), { target: { value: '/same/' } })
    fireEvent.click(screen.getByRole('button', { name: 'Сохранить' }))

    expect(await screen.findByText('Источник и цель совпадают')).toBeTruthy()
    expect(apiRequest.mock.calls.some(([, options]) => options?.method === 'POST')).toBe(false)
  })

  it('creates a redirect and surfaces server warnings', async () => {
    renderWithProviders(<RedirectsTab />)
    await screen.findByTestId('redirect-row')

    fireEvent.click(screen.getByRole('button', { name: 'Добавить редирект' }))
    fireEvent.change(screen.getByLabelText('Старый URL'), { target: { value: '/old-fence/' } })
    fireEvent.change(screen.getByLabelText('Новый URL'), { target: { value: '/fence/' } })
    fireEvent.click(screen.getByRole('button', { name: 'Сохранить' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/seo/redirects', {
        method: 'POST',
        body: { sourcePath: '/old-fence/', targetPath: '/fence/', statusCode: 301, isActive: true },
      })
    })
    expect(await screen.findByText('Редирект создан')).toBeTruthy()
    expect(await screen.findByText(/Цепочка редиректов/)).toBeTruthy()
  })

  it('shows server-side validation errors next to the field', async () => {
    apiRequest.mockImplementation((url: string, options?: { method?: string }) => {
      if (options?.method === 'POST') {
        return Promise.reject(new ApiError('Редирект с таким исходным URL уже существует.', 422, {
          error: 'Редирект с таким исходным URL уже существует.',
          code: 'REDIRECT_DUPLICATE',
          details: [{ field: 'sourcePath', message: 'Редирект с таким исходным URL уже существует.' }],
        }))
      }
      return Promise.resolve(url.startsWith('/admin/api/seo/redirects?') ? listResponse() : {})
    })
    renderWithProviders(<RedirectsTab />)
    await screen.findByTestId('redirect-row')

    fireEvent.click(screen.getByRole('button', { name: 'Добавить редирект' }))
    fireEvent.change(screen.getByLabelText('Старый URL'), { target: { value: '/dup/' } })
    fireEvent.change(screen.getByLabelText('Новый URL'), { target: { value: '/target/' } })
    fireEvent.click(screen.getByRole('button', { name: 'Сохранить' }))

    const messages = await screen.findAllByText('Редирект с таким исходным URL уже существует.')
    expect(messages.length).toBeGreaterThan(0)
  })

  it('asks for confirmation before deleting a redirect', async () => {
    renderWithProviders(<RedirectsTab />)
    const row = await screen.findByTestId('redirect-row')

    fireEvent.click(within(row).getByRole('button', { name: 'Удалить' }))
    expect(apiRequest.mock.calls.some(([, options]) => options?.method === 'DELETE')).toBe(false)

    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Удалить' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith(`/admin/api/seo/redirects/${redirect.id}`, { method: 'DELETE' })
    })
  })

  it('renders loops and chains from the analysis and fixes a chain', async () => {
    const analysis = {
      activeRules: 3,
      loops: [{ path: ['/a/', '/b/', '/a/'], rules: [{ id: 'L1', sourcePath: '/a/', targetPath: '/b/', statusCode: 301 }, { id: 'L2', sourcePath: '/b/', targetPath: '/a/', statusCode: 301 }] }],
      chains: [{
        path: ['/h/', '/m/', '/final/'],
        finalTarget: '/final/',
        rules: [{ id: 'C1', sourcePath: '/h/', targetPath: '/m/', statusCode: 301 }, { id: 'C2', sourcePath: '/m/', targetPath: '/final/', statusCode: 301 }],
      }],
    }
    const base = apiRequest.getMockImplementation()
    apiRequest.mockImplementation((url: string, options?: { method?: string }) => {
      if (url === '/admin/api/seo/redirects/analysis') {
        return Promise.resolve(analysis)
      }
      if (options?.method === 'PUT') {
        return Promise.resolve({ ...redirect, warnings: [] })
      }
      return base?.(url, options)
    })
    renderWithProviders(<RedirectsTab />)
    await screen.findByTestId('redirect-row')

    fireEvent.click(screen.getByRole('button', { name: 'Проверить все активные редиректы' }))

    expect(await screen.findByText('/a/ → /b/ → /a/')).toBeTruthy()
    expect(screen.getByText('/h/ → /m/ → /final/')).toBeTruthy()

    fireEvent.click(screen.getByRole('button', { name: 'Вести напрямую на /final/' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/seo/redirects/C1', {
        method: 'PUT',
        body: { targetPath: '/final/', statusCode: 301, isActive: true },
      })
    })
    expect(apiRequest.mock.calls.some(([url]) => url === '/admin/api/seo/redirects/C2')).toBe(false)
  })
})
