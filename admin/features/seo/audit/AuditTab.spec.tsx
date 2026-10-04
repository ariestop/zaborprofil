import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test-utils'
import { AuditTab } from './AuditTab'

const apiRequest = vi.fn()

vi.mock('../../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const pages = [
  { id: 'P1', type: 'landing', title: 'Заборы', slug: 'zabory', path: '/zabory/', h1: 'Заборы', status: 'published', template: 'x', sortOrder: 0, isIndexable: true, visibility: 'public', publishedAt: null, scheduledPublishAt: null, scheduledUnpublishAt: null, seo: {} },
  { id: 'P2', type: 'landing', title: 'Ворота', slug: 'vorota', path: '/vorota/', h1: 'Ворота', status: 'draft', template: 'x', sortOrder: 1, isIndexable: true, visibility: 'public', publishedAt: null, scheduledPublishAt: null, scheduledUnpublishAt: null, seo: {} },
  { id: 'P3', type: 'landing', title: 'Удалена', slug: 'del', path: '/del/', h1: 'x', status: 'deleted', template: 'x', sortOrder: 2, isIndexable: true, visibility: 'public', publishedAt: null, scheduledPublishAt: null, scheduledUnpublishAt: null, seo: {} },
]

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string) => {
    if (url === '/admin/api/content/pages') {
      return Promise.resolve({ pages })
    }
    if (url === '/admin/api/seo/audit/pages/P1') {
      return Promise.resolve({
        pageId: 'P1',
        path: '/zabory/',
        passed: false,
        issues: [
          { severity: 'P1', code: 'seo.content.faq_empty', message: 'FAQ block must contain at least one question and answer.', field: 'blocks' },
          { severity: 'P2', code: 'seo.og_image.missing', message: 'OpenGraph image is recommended.', field: 'ogImage' },
        ],
      })
    }
    return Promise.resolve({ pageId: 'P2', path: '/vorota/', passed: true, issues: [] })
  })
})

afterEach(cleanup)

describe('AuditTab', () => {
  it('lists non-deleted pages with a link to the page editor', async () => {
    renderWithProviders(<AuditTab />)

    const rows = await screen.findAllByTestId('audit-row')
    expect(rows).toHaveLength(2)
    expect(within(rows[0]).getByRole('link', { name: 'Открыть' }).getAttribute('href')).toBe('/admin/pages/P1')
    expect(screen.getByText('Проверено страниц: 0 из 2')).toBeTruthy()
  })

  it('runs the existing audit endpoint for a page and shows severity counts and details', async () => {
    renderWithProviders(<AuditTab />)
    const rows = await screen.findAllByTestId('audit-row')

    fireEvent.click(within(rows[0]).getByRole('button', { name: 'Проверить' }))

    expect(await within(rows[0]).findByText('P1: 1')).toBeTruthy()
    expect(within(rows[0]).getByText('P2: 1')).toBeTruthy()
    expect(within(rows[0]).getByText('Блокирует публикацию')).toBeTruthy()

    fireEvent.click(within(rows[0]).getByRole('button', { name: 'Замечания' }))
    const list = await screen.findByLabelText('Замечания SEO-аудита')
    expect(within(list).getAllByRole('listitem')).toHaveLength(2)
  })

  it('audits all published pages only', async () => {
    renderWithProviders(<AuditTab />)
    await screen.findAllByTestId('audit-row')

    fireEvent.click(screen.getByRole('button', { name: 'Проверить все опубликованные' }))

    await waitFor(() => expect(screen.getByText('Проверено страниц: 1 из 2')).toBeTruthy())
    const auditCalls = apiRequest.mock.calls.filter(([url]) => String(url).startsWith('/admin/api/seo/audit/pages/'))
    expect(auditCalls.map(([url]) => url)).toEqual(['/admin/api/seo/audit/pages/P1'])
  })
})
