import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../shared/api/client'
import { renderWithProviders } from '../features/seo/test-utils'
import CrmPage from './CrmPage'

const apiRequest = vi.fn()
const downloadTextFile = vi.fn()

vi.mock('../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

vi.mock('../shared/lib/download', () => ({
  downloadTextFile: (...args: unknown[]) => downloadTextFile(...args),
}))

const lead = {
  id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
  source: 'public_page_form',
  name: 'Анна Петрова',
  phone: '+7 900 111-22-33',
  email: 'anna@example.test',
  status: 'new',
  assignee: { id: 'U1', email: 'manager@example.test' },
  spamScore: 0,
  messagePreview: 'Нужен забор на участок',
  createdAt: '2026-10-01T10:00:00+00:00',
  updatedAt: '2026-10-01T10:00:00+00:00',
}

function listResponse(items = [lead]) {
  return {
    items,
    total: items.length,
    page: 1,
    perPage: 25,
    pages: 1,
    counts: { total: 9, byStatus: { new: 4, in_progress: 3, done: 1, spam: 1 } },
    statuses: ['new', 'in_progress', 'done', 'spam'],
    sources: ['callback', 'public_page_form'],
  }
}

function requestedListUrls(): string[] {
  return apiRequest.mock.calls
    .map((call) => String(call[0]))
    .filter((url) => url.startsWith('/admin/api/leads?'))
}

beforeEach(() => {
  apiRequest.mockReset()
  downloadTextFile.mockReset()
  apiRequest.mockImplementation((url: string) => {
    if (url.startsWith('/admin/api/leads?')) {
      return Promise.resolve(listResponse())
    }
    if (url === '/admin/api/leads/assignees') {
      return Promise.resolve({ items: [{ id: 'U1', email: 'manager@example.test' }] })
    }
    if (url.startsWith('/admin/api/leads/export')) {
      return Promise.resolve('id,name\n1,Anna\n')
    }

    return Promise.resolve({})
  })
})

afterEach(cleanup)

describe('CrmPage', () => {
  it('lists leads with status counters and a link to the card', async () => {
    renderWithProviders(<CrmPage />, '/admin/crm')

    const row = await screen.findByTestId('lead-row')
    expect(within(row).getByText('+7 900 111-22-33')).toBeTruthy()
    expect(within(row).getByText('manager@example.test')).toBeTruthy()
    expect(within(row).getByRole('link', { name: 'Анна Петрова' }).getAttribute('href')).toBe(`/admin/crm/${lead.id}`)
    expect(screen.getByRole('button', { name: 'Все · 9' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Новые · 4' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Спам · 1' })).toBeTruthy()
  })

  it('requests the server with the chosen status filter and resets to the first page', async () => {
    renderWithProviders(<CrmPage />, '/admin/crm?page=3')
    await screen.findByTestId('lead-row')
    expect(requestedListUrls().some((url) => url.includes('page=3'))).toBe(true)

    fireEvent.click(screen.getByRole('button', { name: /^В работе/ }))

    await waitFor(() => {
      const last = requestedListUrls().at(-1) ?? ''
      expect(last).toContain('status=in_progress')
      expect(last).toContain('page=1')
    })
  })

  it('sends a debounced search query to the server', async () => {
    renderWithProviders(<CrmPage />, '/admin/crm')
    await screen.findByTestId('lead-row')

    fireEvent.change(screen.getByLabelText('Поиск заявок'), { target: { value: ' 900 111 ' } })

    await waitFor(() => {
      expect(requestedListUrls().at(-1)).toContain('q=900+111')
    }, { timeout: 2000 })
  })

  it('passes filters and sort to the CSV export and downloads the file', async () => {
    renderWithProviders(<CrmPage />, '/admin/crm?status=spam&sort=name&direction=asc')
    await screen.findByTestId('lead-row')

    fireEvent.click(screen.getByRole('button', { name: 'Экспорт CSV' }))

    await waitFor(() => expect(downloadTextFile).toHaveBeenCalledTimes(1))
    const exportUrl = String(apiRequest.mock.calls.find((call) => String(call[0]).startsWith('/admin/api/leads/export'))?.[0])
    expect(exportUrl).toContain('status=spam')
    expect(exportUrl).toContain('sort=name')
    expect(exportUrl).toContain('direction=asc')
    expect(exportUrl).not.toContain('page=')
    expect(downloadTextFile.mock.calls[0]?.[1]).toBe('id,name\n1,Anna\n')
  })

  it('shows an error toast when the export is forbidden', async () => {
    apiRequest.mockImplementation((url: string) => {
      if (url.startsWith('/admin/api/leads/export')) {
        return Promise.reject(new ApiError('Access denied.', 403, { error: 'Access denied.', code: 'ACCESS_DENIED' }))
      }
      if (url === '/admin/api/leads/assignees') {
        return Promise.resolve({ items: [] })
      }

      return Promise.resolve(listResponse())
    })
    renderWithProviders(<CrmPage />, '/admin/crm')
    await screen.findByTestId('lead-row')

    fireEvent.click(screen.getByRole('button', { name: 'Экспорт CSV' }))

    expect(await screen.findByText('Access denied.')).toBeTruthy()
    expect(downloadTextFile).not.toHaveBeenCalled()
  })

  it('shows an empty state with a reset button when filters match nothing', async () => {
    apiRequest.mockImplementation((url: string) => {
      if (url.startsWith('/admin/api/leads?')) {
        return Promise.resolve(listResponse([]))
      }

      return Promise.resolve({ items: [] })
    })
    renderWithProviders(<CrmPage />, '/admin/crm?status=done')

    expect(await screen.findByText('Ничего не найдено')).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Сбросить фильтры' })).toBeTruthy()
  })
})
