import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ToastProvider } from '../app/providers/toast-provider'
import '../features/seo/test-utils'
import type { LeadDetail } from '../types/api'
import LeadDetailPage from './LeadDetailPage'

const apiRequest = vi.fn()

vi.mock('../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const leadId = '01ARZ3NDEKTSV4RRFFQ69G5FAV'

const detail: LeadDetail = {
  id: leadId,
  source: 'callback',
  name: 'Анна Петрова',
  phone: '+7 900 111-22-33',
  email: null,
  message: 'Нужен забор',
  consentSnapshot: { consent: true },
  status: 'new',
  assignee: null,
  spamScore: 0,
  spamReasons: [],
  pageUrl: 'https://zaborprofil.ru/zabory/proflist?x=1',
  utm: { source: 'yandex', medium: 'cpc', campaign: 'zabor-proflist' },
  b2b: false,
  readAt: '2026-10-01T10:01:00+00:00',
  createdAt: '2026-10-01T10:00:00+00:00',
  updatedAt: '2026-10-01T10:00:00+00:00',
  events: [
    {
      id: 'E1',
      type: 'status_changed',
      actorId: null,
      actorLabel: 'admin@example.test',
      body: null,
      data: { from: 'new', to: 'in_progress' },
      createdAt: '2026-10-01T11:00:00+00:00',
    },
  ],
}

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>
      <ToastProvider>
        <MemoryRouter initialEntries={[`/admin/crm/${leadId}`]}>
          <Routes>
            <Route path="/admin/crm/:leadId" element={<LeadDetailPage />} />
          </Routes>
        </MemoryRouter>
      </ToastProvider>
    </QueryClientProvider>,
  )
}

let current = detail

beforeEach(() => {
  current = detail
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string, options?: { method?: string, body?: unknown }) => {
    if (url === '/admin/api/leads/assignees') {
      return Promise.resolve({ items: [{ id: 'U1', email: 'manager@example.test' }] })
    }
    if (options?.method === 'POST' && url.endsWith('/notes')) {
      const text = (options.body as { text: string }).text
      current = {
        ...detail,
        events: [
          ...detail.events,
          { id: 'E2', type: 'note', actorId: null, actorLabel: 'admin@example.test', body: text, data: {}, createdAt: '2026-10-01T12:00:00+00:00' },
        ],
      }

      return Promise.resolve(current)
    }

    return Promise.resolve(current)
  })
})

afterEach(cleanup)

describe('LeadDetailPage', () => {
  it('shows the lead data and the history', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Анна Петрова' })).toBeTruthy()
    expect(screen.getByText(/\+7 900 111-22-33/)).toBeTruthy()
    expect(screen.getByText('Нужен забор')).toBeTruthy()
    expect(screen.getByText('/zabory/proflist')).toBeTruthy()
    expect(screen.getByText('yandex / cpc / zabor-proflist')).toBeTruthy()
    const events = screen.getAllByTestId('lead-event')
    expect(within(events[0]!).getByText('Заявка получена с формы на странице /zabory/proflist')).toBeTruthy()
    expect(within(events[1]!).getByText('Статус: в работе')).toBeTruthy()
  })

  it('adds a manager note and clears the form', async () => {
    renderPage()
    await screen.findByRole('heading', { name: 'Анна Петрова' })

    const submit = screen.getByRole('button', { name: 'Добавить заметку' })
    expect((submit as HTMLButtonElement).disabled).toBe(true)

    fireEvent.change(screen.getByLabelText('Заметка для команды'), { target: { value: 'Перезвонить завтра' } })
    fireEvent.click(submit)

    await waitFor(() => expect(screen.getAllByTestId('lead-event')).toHaveLength(3))
    expect(screen.getByText(/Заметка: Перезвонить завтра/)).toBeTruthy()
    expect((screen.getByLabelText('Заметка для команды') as HTMLTextAreaElement).value).toBe('')
    const noteCall = apiRequest.mock.calls.find((call) => String(call[0]).endsWith('/notes'))
    expect(noteCall?.[1]).toMatchObject({ method: 'POST', body: { text: 'Перезвонить завтра' } })
  })

  it('shows an error state when the lead cannot be loaded', async () => {
    apiRequest.mockRejectedValue(new Error('not found'))
    renderPage()

    expect(await screen.findByText('Не удалось загрузить заявку')).toBeTruthy()
  })

  it('moves a new lead to «В работе» with one click', async () => {
    renderPage()
    await screen.findByRole('heading', { name: 'Анна Петрова' })

    fireEvent.click(screen.getByRole('button', { name: 'Взять в работу' }))

    await waitFor(() => {
      const statusCall = apiRequest.mock.calls.find((call) => String(call[0]).endsWith('/status'))
      expect(statusCall?.[1]).toMatchObject({ method: 'PATCH', body: { status: 'in_progress' } })
    })
  })

  it('shows consent and anti-spam in plain words', async () => {
    renderPage()
    await screen.findByRole('heading', { name: 'Анна Петрова' })

    expect(screen.getByText('Получено при отправке формы')).toBeTruthy()
    expect(screen.getByText('Чисто')).toBeTruthy()
    expect(screen.getByText('Спам-балл 0 из 10')).toBeTruthy()
    expect(screen.queryByRole('link', { name: 'Написать' })).toBeNull()
  })

  it('marks an unread lead as read once and says when consent was not collected', async () => {
    current = { ...detail, readAt: null, source: 'phone_call', consentSnapshot: { consent: false, origin: 'manual' }, pageUrl: null, utm: {}, spamScore: 120, spamReasons: ['honeypot_filled'] }
    renderPage()
    await screen.findByRole('heading', { name: 'Анна Петрова' })

    await waitFor(() => {
      const reads = apiRequest.mock.calls.filter((call) => String(call[0]).endsWith('/read'))
      expect(reads).toHaveLength(1)
    })
    expect(screen.getByText('Согласие не зафиксировано')).toBeTruthy()
    expect(screen.getByText('Высокий риск спама')).toBeTruthy()
    expect(screen.getByText('Спам-балл 10 из 10: honeypot_filled')).toBeTruthy()
    expect(screen.getByText('Заявка создана вручную после звонка')).toBeTruthy()
  })
})
