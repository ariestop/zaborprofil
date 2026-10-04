import { cleanup, fireEvent, screen, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../features/seo/test-utils'
import { SidebarNav } from './SidebarNav'

const apiRequest = vi.fn()

vi.mock('../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

beforeEach(() => {
  window.localStorage.clear()
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string) => {
    if (url === '/admin/api/leads/summary') {
      return Promise.resolve({ total: 9, new: 4, byStatus: { new: 4, in_progress: 3, done: 1, spam: 1 }, statuses: [] })
    }

    return Promise.resolve({})
  })
})

afterEach(cleanup)

describe('SidebarNav', () => {
  it('groups sections and shows the number of new leads next to «Заявки»', async () => {
    renderWithProviders(<SidebarNav />, '/admin/dashboard')

    const nav = screen.getByRole('navigation', { name: 'Разделы админки' })
    expect(within(nav).getByText('Контент')).toBeTruthy()
    expect(within(nav).getByText('Управление')).toBeTruthy()
    expect(within(nav).getByRole('link', { name: 'Сводка' }).getAttribute('aria-current')).toBe('page')

    const leads = within(nav).getByRole('link', { name: /Заявки/ })
    expect(await within(leads).findByText('4')).toBeTruthy()
  })

  it('keeps server sections folded until the group is opened', () => {
    renderWithProviders(<SidebarNav />, '/admin/dashboard')

    const toggle = screen.getByRole('button', { name: 'Сервер' })
    expect(toggle.getAttribute('aria-expanded')).toBe('false')
    expect(screen.queryByRole('link', { name: 'Логи' })).toBeNull()

    fireEvent.click(toggle)

    expect(toggle.getAttribute('aria-expanded')).toBe('true')
    expect(screen.getByRole('link', { name: 'Логи' }).getAttribute('href')).toBe('/admin/system/logs')
  })

  it('opens the server group automatically on a server page', () => {
    renderWithProviders(<SidebarNav />, '/admin/system/backups')

    expect(screen.getByRole('button', { name: 'Сервер' }).getAttribute('aria-expanded')).toBe('true')
    expect(screen.getByRole('link', { name: 'Резервные копии' }).getAttribute('aria-current')).toBe('page')
  })

  it('highlights «Заявки» on a lead card', () => {
    renderWithProviders(<SidebarNav />, '/admin/crm/01ARZ3NDEKTSV4RRFFQ69G5FAV')

    expect(screen.getByRole('link', { name: /Заявки/ }).getAttribute('aria-current')).toBe('page')
  })

  it('renders an icon-only rail with accessible names when collapsed', () => {
    renderWithProviders(<SidebarNav collapsed />, '/admin/pages')

    expect(screen.getByRole('link', { name: 'Страницы' }).getAttribute('aria-current')).toBe('page')
    expect(screen.queryByText('Контент')).toBeNull()
    expect(screen.getByRole('link', { name: 'Сервер' }).getAttribute('href')).toBe('/admin/system')
  })
})
