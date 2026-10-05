import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { useState, type ReactNode } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { TopbarSlotContext } from '../layouts/topbar-slot'
import { renderWithProviders as render } from '../features/seo/test-utils'
import { loginAs } from '../shared/testing/roles'
import DashboardPage from './DashboardPage'

function TopbarHost({ children }: { children: ReactNode }) {
  const [slot, setSlot] = useState<HTMLDivElement | null>(null)

  return (
    <>
      <div ref={setSlot} data-testid="topbar-slot" />
      <TopbarSlotContext.Provider value={slot}>{children}</TopbarSlotContext.Provider>
    </>
  )
}

const apiRequest = vi.fn()

vi.mock('../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const lead = {
  id: 'L1',
  source: 'public_page_form',
  name: 'Андрей Смирнов',
  phone: '+7 900 000-12-45',
  email: null,
  status: 'new',
  assignee: null,
  spamScore: 0,
  b2b: false,
  readAt: null,
  pageUrl: 'https://zaborprofil.ru/zabory/proflist',
  messagePreview: 'Забор из профлиста С8, 1,8 м, около 120 м.',
  createdAt: new Date().toISOString(),
  updatedAt: new Date().toISOString(),
}

const days = Array.from({ length: 14 }, (_, index) => ({ date: `2026-09-${String(21 + index).padStart(2, '0')}`, count: index === 13 ? 4 : 2 }))

function respond(url: string) {
  if (url.startsWith('/admin/api/leads?')) {
    return { items: [lead, { ...lead, id: 'L2', name: 'ООО «СтройДвор»', b2b: true, pageUrl: 'https://zaborprofil.ru/', messagePreview: null }], total: 2, page: 1, perPage: 4, pages: 1, counts: { total: 2, byStatus: {} }, statuses: [], sources: [] }
  }
  if (url === '/admin/api/leads/summary') {
    return { total: 20, new: 2, byStatus: { new: 2, in_progress: 7, done: 10, spam: 1 }, statuses: ['new', 'in_progress', 'done', 'spam'] }
  }
  if (url === '/admin/api/leads/dashboard') {
    return { daily: days, createdLast14Days: 32, doneLastWeek: 9, oldestNewAt: new Date(Date.now() - 192 * 60_000).toISOString() }
  }
  if (url === '/admin/api/content/pages') {
    return { pages: [{ id: 'p1', status: 'published' }, { id: 'p2', status: 'draft' }] }
  }
  if (url === '/admin/api/system/overview') {
    return { status: 'ok', environment: { appEnv: 'staging', phpVersion: '8.5' }, warnings: [{ code: 'health_disk_warning', severity: 'warning', message: 'Диск: занято 97%.' }] }
  }
  if (url === '/admin/api/system/backups') {
    return { backupDirectory: '/b', latestBackup: null, files: [], checkedAt: '2026-10-04T10:00:00+00:00' }
  }
  if (url === '/admin/api/system/observability') {
    return {
      serverErrors: { lastHour: 0, last24Hours: 0 },
      queue: { pending: 0, failed: 0 },
      disk: { status: 'warning', freeBytes: 210 * 1024 ** 3, totalBytes: 7000 * 1024 ** 3, usedPercent: 97.01 },
      checkedAt: '2026-10-04T10:00:00+00:00',
    }
  }
  if (url === '/admin/api/system/assets/build') {
    return { status: 'failed', command: 'npm run build', selectedTargets: ['all'], availableTargets: [], startedAt: null, finishedAt: null, exitCode: 1, progress: 0, logs: '' }
  }

  return {}
}

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string) => Promise.resolve(respond(url)))
  loginAs('ROLE_ADMIN')
})

afterEach(cleanup)

describe('DashboardPage', () => {
  it('greets the user and lists what needs attention by priority', async () => {
    render(<TopbarHost><DashboardPage /></TopbarHost>, '/admin/dashboard')

    expect(screen.getByRole('heading', { level: 1 }).textContent).toMatch(/^(Доброе утро|Добрый день|Добрый вечер|Доброй ночи)$/)
    const list = await screen.findByRole('list', { name: 'Задачи, требующие внимания' })
    await waitFor(() => expect(within(list).getAllByRole('listitem').length).toBe(4))

    const items = within(list).getAllByRole('listitem')
    expect(items[0]!.textContent).toContain('2 новые заявки без ответа')
    expect(items[0]!.textContent).toContain('Самая ранняя ждёт 3 ч')
    expect(items[1]!.textContent).toContain('Сборка админки завершилась с ошибкой')
    expect(within(items[1]!).getByRole('button', { name: 'Пересобрать' })).toBeTruthy()
    expect(items[2]!.textContent).toContain('Резервных копий нет')
    expect(items[3]!.textContent).toContain('Заканчивается место на диске')
  })

  it('shows the newest leads with page origin, B2B badge and quick actions', async () => {
    render(<TopbarHost><DashboardPage /></TopbarHost>, '/admin/dashboard')

    const list = await screen.findByRole('list', { name: 'Новые заявки' })
    const [first, second] = within(list).getAllByRole('listitem')
    expect(first!.textContent).toContain('/zabory/proflist')
    expect(first!.textContent).toContain('Забор из профлиста')
    expect(within(first!).getByRole('link', { name: /Позвонить/ }).getAttribute('href')).toBe('tel:+79000001245')
    expect(second!.textContent).toContain('B2B')
    expect(second!.textContent).toContain('главная')
  })

  it('takes a lead into work and offers to undo', async () => {
    render(<TopbarHost><DashboardPage /></TopbarHost>, '/admin/dashboard')

    fireEvent.click(await screen.findByRole('button', { name: 'Взять в работу: Андрей Смирнов' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/leads/L1/status', { method: 'PATCH', body: { status: 'in_progress' } })
    })
    const toast = await screen.findByRole('status')
    expect(toast.textContent).toContain('Заявка «Андрей Смирнов» взята в работу')

    fireEvent.click(within(toast).getByRole('button', { name: 'Отменить' }))
    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/leads/L1/status', { method: 'PATCH', body: { status: 'new' } })
    })
  })

  it('shows KPI tiles, the 14-day chart, site state and the compact monitoring block', async () => {
    render(<TopbarHost><DashboardPage /></TopbarHost>, '/admin/dashboard')

    expect(await screen.findByRole('img', { name: /за 14 дней, сегодня 4/ })).toBeTruthy()
    expect(screen.getByText('32')).toBeTruthy()
    expect(screen.getByRole('link', { name: /Опубликовано/ }).textContent).toContain('1 / 2')
    await waitFor(() => expect(screen.getByRole('link', { name: /Завершено за неделю/ }).textContent).toContain('9'))

    const state = within(await screen.findByRole('region', { name: 'Состояние сайта' }))
    expect(await state.findByText('OK · PHP 8.5')).toBeTruthy()
    expect(state.getByText('не найдены')).toBeTruthy()
    expect(state.getByText('заканчивается')).toBeTruthy()

    const monitoring = within(await screen.findByRole('region', { name: 'Мониторинг сервера' }))
    expect(monitoring.getAllByRole('link')).toHaveLength(3)
    expect(monitoring.getByText('Ошибки 5xx за 24 ч')).toBeTruthy()
    expect(monitoring.getByText('занято 97,01%')).toBeTruthy()
  })

  it('offers “Создать” in the topbar slot with actions allowed for the role', async () => {
    render(<TopbarHost><DashboardPage /></TopbarHost>, '/admin/dashboard')

    const slot = within(screen.getByTestId('topbar-slot'))
    expect(slot.getByRole('button', { name: 'Создать' })).toBeTruthy()
  })

  it('hides site and page blocks from a manager who only works with leads', async () => {
    loginAs('ROLE_MANAGER')
    render(<TopbarHost><DashboardPage /></TopbarHost>, '/admin/dashboard')

    await screen.findByRole('list', { name: 'Новые заявки' })
    expect(screen.queryByRole('region', { name: 'Состояние сайта' })).toBeNull()
    expect(screen.queryByRole('region', { name: 'Мониторинг сервера' })).toBeNull()
    expect(screen.queryByRole('link', { name: /Опубликовано/ })).toBeNull()
    expect(apiRequest.mock.calls.some((call) => String(call[0]).startsWith('/admin/api/system'))).toBe(false)
  })
})
