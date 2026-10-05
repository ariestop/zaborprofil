import { cleanup, fireEvent, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../features/seo/test-utils'
import SeoPage from './SeoPage'

const apiRequest = vi.fn()

vi.mock('../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string) => {
    if (url.startsWith('/admin/api/seo/redirects?')) {
      return Promise.resolve({ items: [], total: 0, page: 1, perPage: 25, pages: 1, counts: { total: 0, active: 0, inactive: 0 } })
    }
    if (url.startsWith('/admin/api/seo/not-found?')) {
      return Promise.resolve({ items: [], total: 0, totalHits: 0, page: 1, perPage: 25, pages: 1 })
    }
    if (url === '/admin/api/seo/robots') {
      return Promise.resolve({ body: '', effectiveBody: '', defaultBody: 'User-agent: *\n', environment: 'prod', overriddenByEnvironment: false })
    }
    if (url === '/admin/api/seo/robots/preview') {
      return Promise.resolve({ normalizedBody: null, effectiveBody: '', usesDefault: true, overriddenByEnvironment: false, valid: true, issues: [] })
    }
    if (url === '/admin/api/content/pages') {
      return Promise.resolve({ pages: [] })
    }
    return Promise.resolve({})
  })
})

afterEach(cleanup)

describe('SeoPage', () => {
  it('opens the redirects tab by default', async () => {
    renderWithProviders(<SeoPage />)

    expect(screen.getByRole('heading', { name: 'SEO-панель' })).toBeTruthy()
    expect(await screen.findByRole('button', { name: 'Добавить редирект' })).toBeTruthy()
    expect(screen.getByRole('tab', { name: 'Редиректы' }).getAttribute('data-state')).toBe('active')
  })

  it('restores the tab from the url', async () => {
    renderWithProviders(<SeoPage />, '/admin/seo?tab=not-found')

    expect(await screen.findByText('Журнал пуст')).toBeTruthy()
    expect(screen.getByRole('tab', { name: 'Журнал 404' }).getAttribute('data-state')).toBe('active')
  })

  it('switches between all four tabs', async () => {
    renderWithProviders(<SeoPage />)

    const tabs: Array<[string, () => Promise<unknown>]> = [
      ['robots.txt', () => screen.findByLabelText('Содержимое robots.txt')],
      ['Журнал 404', () => screen.findByLabelText('Поиск по журналу 404')],
      ['SEO-аудит', () => screen.findByRole('button', { name: 'Проверить все опубликованные' })],
    ]
    for (const [name, findContent] of tabs) {
      fireEvent.mouseDown(screen.getByRole('tab', { name }), { button: 0 })
      await waitFor(() => expect(screen.getByRole('tab', { name }).getAttribute('data-state')).toBe('active'))
      expect(await findContent()).toBeTruthy()
    }
  })
})
