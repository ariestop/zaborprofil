import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { PortfolioCategory, PortfolioProjectSummary } from '../entities/portfolio/api'
import { renderWithProviders } from '../features/seo/test-utils'
import { initializeAuthStore } from '../stores/auth'
import PortfolioPage from './PortfolioPage'

const apiRequest = vi.fn()

vi.mock('../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

function category(id: string, title: string, sortOrder: number): PortfolioCategory {
  return { id, slug: id, title, shortTitle: null, description: null, sortOrder, path: `/portfolio/${id}/`, updatedAt: '2026-10-15T10:00:00+00:00' }
}

function project(id: string, categoryId: string, title: string, sortOrder: number, extra: Partial<PortfolioProjectSummary> = {}): PortfolioProjectSummary {
  return {
    id, categoryId, slug: id, title, place: 'Саратов', year: 2025, summary: null, specs: [], status: 'published', sortOrder,
    seoTitle: null, seoDescription: null, path: `/portfolio/${categoryId}/${id}/`, cover: `/uploads/media/${id}.jpg`, photoCount: 3, videoCount: 0,
    publishedAt: null, updatedAt: '2026-10-15T10:00:00+00:00', ...extra,
  }
}

const overview = {
  categories: [category('profnastil', 'Заборы из профнастила', 20), category('jaluzi', 'Заборы Жалюзи', 10)],
  projects: [
    project('p2', 'jaluzi', 'Жалюзи у беседки', 20, { status: 'draft', photoCount: 2, videoCount: 1 }),
    project('p1', 'jaluzi', 'Жалюзи графит', 10),
    project('p3', 'profnastil', 'Профнастил RAL 6005', 10),
  ],
}

beforeEach(() => {
  initializeAuthStore({ userEmail: 'editor@example.test', logoutUrl: '/admin/logout', logoutToken: 't', permissions: ['portfolio.view', 'portfolio.manage'] })
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string) => (url === '/admin/api/portfolio' ? Promise.resolve(overview) : Promise.resolve({})))
})

afterEach(cleanup)

const projectTitles = () =>
  Array.from(screen.getByTestId('portfolio-projects').querySelectorAll('a[href^="/admin/portfolio/"]')).map((link) => link.textContent ?? '')

describe('PortfolioPage', () => {
  it('lists sections in order and the works of the selected section', async () => {
    renderWithProviders(<PortfolioPage />, '/admin/portfolio')

    await screen.findByTestId('portfolio-projects')
    const sections = within(screen.getByRole('region', { name: 'Разделы портфолио' })).getAllByRole('button', { name: /работ/ })
    expect(sections.map((button) => button.textContent)).toEqual(['Заборы Жалюзи2 работы', 'Заборы из профнастила1 работа'])
    expect(projectTitles()[0]).toContain('Жалюзи графит')
    expect(projectTitles()[1]).toContain('Черновик')
    expect(projectTitles()[1]).toContain('2 фото · 1 видео')

    fireEvent.click(sections[1])
    await waitFor(() => expect(projectTitles()).toHaveLength(1))
    expect(projectTitles()[0]).toContain('Профнастил RAL 6005')
  })

  it('reorders works with the arrow buttons and publishes a draft', async () => {
    renderWithProviders(<PortfolioPage />, '/admin/portfolio')
    await screen.findByTestId('portfolio-projects')

    fireEvent.click(screen.getByRole('button', { name: 'Ниже: Жалюзи графит' }))
    await waitFor(() =>
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/portfolio/categories/jaluzi/projects-order', { method: 'PUT', body: { ids: ['p2', 'p1'] } }),
    )

    fireEvent.click(screen.getByRole('switch', { name: 'Опубликовать «Жалюзи у беседки»' }))
    await waitFor(() =>
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/portfolio/projects/p2/status', { method: 'PUT', body: { published: true } }),
    )
  })

  it('creates a section with an address made from the title', async () => {
    apiRequest.mockImplementation((url: string, options?: { method?: string }) => {
      if (url === '/admin/api/portfolio/categories' && options?.method === 'POST') {
        return Promise.resolve(category('vorota', 'Ворота и калитки', 30))
      }
      return url === '/admin/api/portfolio' ? Promise.resolve(overview) : Promise.resolve({})
    })
    renderWithProviders(<PortfolioPage />, '/admin/portfolio')
    await screen.findByTestId('portfolio-projects')

    fireEvent.click(screen.getByRole('button', { name: 'Новый раздел' }))
    fireEvent.change(screen.getByLabelText('Название'), { target: { value: 'Ворота и калитки' } })
    expect((screen.getByLabelText('Адрес раздела') as HTMLInputElement).value).toBe('vorota-i-kalitki')
    fireEvent.click(screen.getByRole('button', { name: 'Создать раздел' }))

    await waitFor(() =>
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/portfolio/categories', {
        method: 'POST',
        body: { title: 'Ворота и калитки', slug: 'vorota-i-kalitki', shortTitle: null, description: null },
      }),
    )
  })

  it('hides management controls without the manage permission', async () => {
    initializeAuthStore({ userEmail: 'seo@example.test', logoutUrl: '/admin/logout', logoutToken: 't', permissions: ['portfolio.view'] })
    renderWithProviders(<PortfolioPage />, '/admin/portfolio')
    await screen.findByTestId('portfolio-projects')

    expect(screen.queryByRole('button', { name: /Новая работа/ })).toBeNull()
    expect(screen.queryByRole('switch')).toBeNull()
    expect(screen.queryByRole('button', { name: /Удалить/ })).toBeNull()
  })
})
