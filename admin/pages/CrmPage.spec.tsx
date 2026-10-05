import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { useState, type ReactNode } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { TopbarSlotContext } from '../layouts/topbar-slot'
import { ApiError } from '../shared/api/client'
import { renderWithProviders as render } from '../features/seo/test-utils'
import CrmPage from './CrmPage'

/** В приложении действия страницы выводятся в шапку; в тесте шапку заменяет обычный контейнер. */
function TopbarHost({ children }: { children: ReactNode }) {
  const [slot, setSlot] = useState<HTMLDivElement | null>(null)

  return (
    <>
      <div ref={setSlot} data-testid="topbar-slot" />
      <TopbarSlotContext.Provider value={slot}>{children}</TopbarSlotContext.Provider>
    </>
  )
}

function renderWithProviders(ui: ReactNode, entry: string) {
  return render(<TopbarHost>{ui}</TopbarHost>, entry)
}

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
  b2b: false,
  readAt: null as string | null,
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
    if (url === `/admin/api/leads/${lead.id}`) {
      return Promise.resolve({
        ...lead,
        message: 'Нужен забор на участок 12 соток',
        consentSnapshot: { consent: true },
        spamReasons: [],
        pageUrl: 'https://zaborprofil.ru/zabory/proflist',
        utm: { source: 'yandex', medium: 'cpc' },
        events: [],
      })
    }

    return Promise.resolve({})
  })
})

afterEach(cleanup)

describe('CrmPage', () => {
  it('lists leads with tabs, counters, unread marker and B2B badge', async () => {
    apiRequest.mockImplementation((url: string) => {
      if (url.startsWith('/admin/api/leads?')) {
        return Promise.resolve(listResponse([lead, { ...lead, id: 'B2', name: 'ООО «СтройДвор»', b2b: true, readAt: '2026-10-01T10:05:00+00:00' }]))
      }

      return Promise.resolve({ items: [] })
    })
    renderWithProviders(<CrmPage />, '/admin/crm')

    const rows = await screen.findAllByTestId('lead-row')
    const first = within(rows[0]!)
    expect(first.getByText('Анна Петрова')).toBeTruthy()
    expect(first.getByText('АП')).toBeTruthy()
    expect(first.getByText('Нужен забор на участок')).toBeTruthy()
    expect(first.getByText('Форма на странице')).toBeTruthy()
    expect(first.getByLabelText('Не прочитана')).toBeTruthy()
    expect(first.getByRole('link').getAttribute('href')).toBe(`/admin/crm/${lead.id}`)
    const second = within(rows[1]!)
    expect(second.getByText('B2B')).toBeTruthy()
    expect(second.queryByLabelText('Не прочитана')).toBeNull()

    const tabs = within(screen.getByRole('tablist', { name: 'Статус заявок' }))
    expect(tabs.getByRole('tab', { name: /^Все\s*8$/ })).toBeTruthy()
    expect(tabs.getByRole('tab', { name: /^Новые\s*4$/ })).toBeTruthy()
    expect(tabs.getByRole('tab', { name: /^Спам\s*1$/ })).toBeTruthy()
  })

  it('requests the server with the chosen status filter and resets to the first page', async () => {
    renderWithProviders(<CrmPage />, '/admin/crm?page=3')
    await screen.findByTestId('lead-row')
    expect(requestedListUrls().some((url) => url.includes('page=3') && url.includes('status=active'))).toBe(true)

    fireEvent.click(screen.getByRole('tab', { name: /^В работе/ }))

    await waitFor(() => {
      const last = requestedListUrls().at(-1) ?? ''
      expect(last).toContain('status=in_progress')
      expect(last).toContain('page=1')
    })
  })

  it('sends a debounced search query to the server', async () => {
    renderWithProviders(<CrmPage />, '/admin/crm')
    await screen.findByTestId('lead-row')

    fireEvent.change(screen.getByLabelText('Поиск по заявкам'), { target: { value: ' 900 111 ' } })

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

  it('opens the lead card next to the list and keeps the filters in the links', async () => {
    renderWithProviders(<CrmPage />, `/admin/crm/${lead.id}?status=new`)

    const row = await screen.findByTestId('lead-row')
    const link = within(row).getByRole('link')
    expect(link.getAttribute('href')).toBe(`/admin/crm/${lead.id}?status=new`)
    expect(link.getAttribute('aria-current')).toBe('true')

    const card = await screen.findByTestId('lead-detail')
    expect(within(card).getByRole('heading', { name: 'Анна Петрова' })).toBeTruthy()
    expect(within(card).getByText('Нужен забор на участок 12 соток')).toBeTruthy()
    expect(within(card).getByText('/zabory/proflist')).toBeTruthy()
    expect(within(card).getByText('yandex / cpc')).toBeTruthy()
    expect(within(card).getByRole('link', { name: 'Позвонить' }).getAttribute('href')).toBe('tel:+79001112233')
  })

  it('shows a hint instead of the card when no lead is selected', async () => {
    renderWithProviders(<CrmPage />, '/admin/crm')
    await screen.findByTestId('lead-row')

    expect(screen.getByText('Выберите заявку слева')).toBeTruthy()
    expect(screen.queryByTestId('lead-detail')).toBeNull()
  })

  it('marks an opened unread lead as read', async () => {
    renderWithProviders(<CrmPage />, `/admin/crm/${lead.id}`)
    await screen.findByTestId('lead-detail')

    await waitFor(() => {
      const call = apiRequest.mock.calls.find((item) => String(item[0]).endsWith('/read'))
      expect(call?.[1]).toMatchObject({ method: 'PATCH' })
    })
  })

  it('moves the lead to the next status with one click and offers to undo it', async () => {
    renderWithProviders(<CrmPage />, `/admin/crm/${lead.id}`)
    await screen.findByTestId('lead-detail')

    fireEvent.click(await screen.findByRole('button', { name: 'Взять в работу' }))

    expect(await screen.findByText('«Анна Петрова» → В работе')).toBeTruthy()
    const statusCalls = () => apiRequest.mock.calls.filter((call) => String(call[0]).endsWith('/status'))
    expect(statusCalls()[0]?.[1]).toMatchObject({ method: 'PATCH', body: { status: 'in_progress' } })

    fireEvent.click(screen.getByRole('button', { name: 'Отменить' }))

    await waitFor(() => expect(statusCalls()).toHaveLength(2))
    expect(statusCalls()[1]?.[1]).toMatchObject({ body: { status: 'new' } })
    await waitFor(() => expect(screen.queryByText('«Анна Петрова» → В работе')).toBeNull())
  })

  it('applies the quick filters to the server request', async () => {
    renderWithProviders(<CrmPage />, '/admin/crm')
    await screen.findByTestId('lead-row')

    fireEvent.click(screen.getByRole('button', { name: 'Юрлица B2B' }))
    await waitFor(() => expect(requestedListUrls().at(-1)).toContain('b2b=1'))

    fireEvent.click(screen.getByRole('button', { name: 'Без ответа больше 2 ч' }))
    await waitFor(() => expect(requestedListUrls().at(-1)).toContain('waiting=2'))

    fireEvent.click(screen.getByRole('button', { name: 'Сегодня' }))
    await waitFor(() => expect(requestedListUrls().at(-1)).toMatch(/from=\d{4}-\d{2}-\d{2}/))
  })

  it('saves the current filters as a named view and applies it later', async () => {
    renderWithProviders(<CrmPage />, '/admin/crm?b2b=1&waiting=2')
    await screen.findByTestId('lead-row')

    fireEvent.click(screen.getByRole('button', { name: '+ Сохранить вид' }))
    fireEvent.change(screen.getByLabelText('Название вида'), { target: { value: 'B2B без ответа' } })
    fireEvent.click(screen.getByRole('button', { name: 'Сохранить' }))
    expect(await screen.findByRole('button', { name: 'B2B без ответа' })).toBeTruthy()

    fireEvent.click(screen.getByRole('button', { name: 'Сбросить фильтры' }))
    await waitFor(() => expect(requestedListUrls().at(-1)).not.toContain('b2b=1'))

    fireEvent.click(screen.getByRole('button', { name: 'B2B без ответа' }))
    await waitFor(() => {
      const last = requestedListUrls().at(-1) ?? ''
      expect(last).toContain('b2b=1')
      expect(last).toContain('waiting=2')
    })
  })

  it('creates a lead after a call from the topbar action', async () => {
    renderWithProviders(<CrmPage />, '/admin/crm')
    await screen.findByTestId('lead-row')

    fireEvent.click(screen.getByRole('button', { name: 'Заявка после звонка' }))
    fireEvent.change(await screen.findByLabelText('Имя клиента'), { target: { value: 'Дмитрий Орлов' } })
    fireEvent.change(screen.getByLabelText('Телефон'), { target: { value: '+7 900 000-29-64' } })
    fireEvent.click(screen.getByRole('button', { name: 'Создать заявку' }))

    await waitFor(() => {
      const call = apiRequest.mock.calls.find((item) => item[0] === '/admin/api/leads' && (item[1] as { method?: string } | undefined)?.method === 'POST')
      expect(call?.[1]).toMatchObject({ body: { name: 'Дмитрий Орлов', phone: '+7 900 000-29-64', email: null, message: null } })
    })
  })
})
