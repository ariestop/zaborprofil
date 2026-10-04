import { cleanup, fireEvent, screen, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../features/seo/test-utils'
import { loginAs } from '../shared/testing/roles'
import { SidebarNav } from './SidebarNav'

const apiRequest = vi.fn()

vi.mock('../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

/** Своё хранилище в памяти: в Node 25 глобальный localStorage без --localstorage-file не поддерживает clear(). */
function installMemoryStorage() {
  const data = new Map<string, string>()
  const storage = {
    getItem: (key: string) => data.get(key) ?? null,
    setItem: (key: string, value: string) => void data.set(key, String(value)),
    removeItem: (key: string) => void data.delete(key),
    clear: () => data.clear(),
    key: (index: number) => Array.from(data.keys())[index] ?? null,
    get length() {
      return data.size
    },
  }
  Object.defineProperty(window, 'localStorage', { value: storage, configurable: true })
}

beforeEach(() => {
  installMemoryStorage()
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

  it('shows a manager only the dashboard and leads', async () => {
    loginAs('ROLE_MANAGER')
    renderWithProviders(<SidebarNav />, '/admin/dashboard')

    const nav = screen.getByRole('navigation', { name: 'Разделы админки' })
    expect(within(nav).getByRole('link', { name: 'Сводка' })).toBeTruthy()
    expect(await within(nav).findByRole('link', { name: /Заявки/ })).toBeTruthy()
    for (const hidden of ['Страницы', 'Медиатека', 'SEO']) {
      expect(within(nav).queryByRole('link', { name: hidden })).toBeNull()
    }
    expect(within(nav).queryByText('Управление')).toBeNull()
    expect(screen.queryByRole('button', { name: 'Сервер' })).toBeNull()
  })

  it('shows an editor pages and media without leads, SEO or the server group, and does not poll leads', () => {
    loginAs('ROLE_EDITOR')
    renderWithProviders(<SidebarNav />, '/admin/dashboard')

    expect(screen.getByRole('link', { name: 'Страницы' })).toBeTruthy()
    expect(screen.getByRole('link', { name: 'Медиатека' })).toBeTruthy()
    expect(screen.queryByRole('link', { name: /Заявки/ })).toBeNull()
    expect(screen.queryByRole('link', { name: 'SEO' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'Сервер' })).toBeNull()
    expect(apiRequest).not.toHaveBeenCalledWith('/admin/api/leads/summary')
  })

  it('shows an SEO specialist pages and SEO', () => {
    loginAs('ROLE_SEO')
    renderWithProviders(<SidebarNav />, '/admin/seo')

    expect(screen.getByRole('link', { name: 'SEO' }).getAttribute('aria-current')).toBe('page')
    expect(screen.getByRole('link', { name: 'Страницы' })).toBeTruthy()
    expect(screen.queryByRole('link', { name: 'Медиатека' })).toBeNull()
  })

  it('keeps users and the server group for administrators', () => {
    loginAs('ROLE_ADMIN')
    renderWithProviders(<SidebarNav />, '/admin/dashboard')

    expect(screen.getByRole('link', { name: 'Пользователи и роли' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Сервер' })).toBeTruthy()
  })
})
