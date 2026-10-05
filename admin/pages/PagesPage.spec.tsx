import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { makePageItem } from '../features/page-editor/fixtures'
import { renderWithProviders } from '../features/seo/test-utils'
import { loginAs } from '../shared/testing/roles'
import type { PageTemplateItem } from '../types/api'
import PagesPage from './PagesPage'

const apiRequest = vi.fn()

vi.mock('../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const pages = [
  makePageItem({ id: 'page-1', title: 'Забор из профнастила', path: '/zabor/', status: 'published', hasUnpublishedChanges: true, updatedAt: '2026-10-01T10:00:00+03:00' }),
  makePageItem({ id: 'page-2', title: 'Ворота', path: '/vorota/', isIndexable: false, updatedAt: '2026-10-03T12:00:00+03:00', updatedBy: '01J0000000000000000000000A', updatedByName: 'Игорь' }),
  makePageItem({ id: 'page-3', title: 'Старая акция', path: '/akciya/', status: 'deleted', updatedAt: '2026-09-01T12:00:00+03:00' }),
]

const serviceTemplate: PageTemplateItem = {
  id: 't1',
  code: 'service_landing',
  name: 'Страница услуги',
  description: 'Описание, цены и заявка',
  kind: 'page',
  pageType: 'service',
  blocksSchema: [
    { type: 'hero.classic', name: 'Первый экран', position: 0, content: {}, settings: {}, isEnabled: true },
    { type: 'price-table', name: 'Цены', position: 1, content: {}, settings: {}, isEnabled: true },
  ],
  defaultSeo: {},
  defaultSettings: {},
  isSystem: true,
  isActive: true,
}

const contactsTemplate: PageTemplateItem = { ...serviceTemplate, id: 't2', code: 'contacts', name: 'Контакты', pageType: 'contacts', blocksSchema: [] }
const sectionTemplate: PageTemplateItem = { ...serviceTemplate, id: 't3', code: 'faq_section', name: 'Секция FAQ', kind: 'section' }

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string, options?: { method?: string, body?: Record<string, unknown> }) => {
    if (url === '/admin/api/content/pages' && options?.method === 'POST') {
      return Promise.resolve(makePageItem({ id: 'page-new', title: String(options.body?.title ?? '') }))
    }
    if (url === '/admin/api/content/pages') {
      return Promise.resolve({ pages })
    }
    if (url === '/admin/api/content/templates') {
      return Promise.resolve({ templates: [serviceTemplate, sectionTemplate, contactsTemplate] })
    }
    if (url === '/admin/api/content/pages/bulk') {
      const ids = (options?.body?.ids ?? []) as string[]
      return Promise.resolve({ results: ids.map((id) => ({ id, ok: true, error: null })), succeeded: ids.length, failed: 0 })
    }
    if (url === '/admin/api/content/pages/page-1/duplicate' && options?.method === 'POST') {
      return Promise.resolve(makePageItem({ id: 'page-4', title: 'Забор из профнастила (копия)', path: '/zabor-copy/' }))
    }
    return Promise.resolve({})
  })
})

afterEach(cleanup)

function rowOf(title: string): HTMLElement {
  const row = screen.getAllByTestId('page-row').find((item) => item.textContent?.includes(title))
  if (row === undefined) {
    throw new Error(`Row «${title}» not found`)
  }

  return row
}

function openRowMenu(title: string): void {
  fireEvent.keyDown(within(rowOf(title)).getByRole('button', { name: `Действия со страницей «${title}»` }), { key: 'Enter' })
}

describe('PagesPage: список', () => {
  it('sorts recently changed pages first and marks noindex and unpublished changes', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')

    const rows = await screen.findAllByTestId('page-row')
    expect(rows).toHaveLength(2)
    expect(rows[0]?.textContent).toContain('Ворота')
    expect(within(rows[0] as HTMLElement).getByText('noindex')).toBeTruthy()
    expect(within(rows[0] as HTMLElement).getByText('Игорь')).toBeTruthy()
    expect(screen.getAllByTestId('page-card')[0]?.textContent).toContain('· Игорь')
    expect(within(rows[1] as HTMLElement).getByText('есть неопубликованные правки')).toBeTruthy()
    expect(screen.getByText('2 страницы · опубликовано 1 · с неопубликованными правками 1')).toBeTruthy()
  })

  it('filters by status tabs with counts; deleted pages have their own tab', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')
    await screen.findAllByTestId('page-row')

    const tabs = within(screen.getByRole('tablist', { name: 'Статус страниц' }))
    expect(tabs.getByRole('tab', { name: 'Все 2' }).getAttribute('aria-selected')).toBe('true')
    fireEvent.click(tabs.getByRole('tab', { name: 'Опубликованы 1' }))
    expect(screen.getAllByTestId('page-row').map((row) => row.textContent)).toEqual([expect.stringContaining('Забор из профнастила')])

    fireEvent.click(tabs.getByRole('tab', { name: 'Удалённые 1' }))
    expect(screen.getAllByTestId('page-row')[0]?.textContent).toContain('Старая акция')
  })

  it('searches by title or address', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')
    await screen.findAllByTestId('page-row')

    fireEvent.change(screen.getByRole('searchbox', { name: 'Поиск по страницам' }), { target: { value: '/vorota' } })
    expect(screen.getAllByTestId('page-row')).toHaveLength(1)
    fireEvent.change(screen.getByRole('searchbox', { name: 'Поиск по страницам' }), { target: { value: 'нет такой' } })
    expect(screen.getByText('Ничего не нашлось. Измените фильтр или поиск.')).toBeTruthy()
  })

  it('applies a bulk action to the selected pages', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')

    await screen.findAllByTestId('page-row')
    expect(screen.queryByRole('region', { name: 'Массовые действия' })).toBeNull()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Выбрать все страницы' }))
    expect((await screen.findByTestId('bulk-count')).textContent).toBe('Выбрано страниц: 2')

    fireEvent.click(screen.getByRole('button', { name: 'Закрыть от индексации' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/bulk', {
        method: 'POST',
        body: { ids: ['page-2', 'page-1'], action: 'indexable', indexable: false },
      })
    })
    await waitFor(() => expect(screen.queryByRole('region', { name: 'Массовые действия' })).toBeNull())
  })

  it('sends the chosen status for the selected pages only', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')

    await screen.findAllByTestId('page-row')
    fireEvent.click(screen.getByRole('checkbox', { name: 'Выбрать «Ворота»' }))
    const bulkBar = await screen.findByRole('region', { name: 'Массовые действия' })
    fireEvent.change(within(bulkBar).getByLabelText('Статус'), { target: { value: 'review' } })
    fireEvent.click(within(bulkBar).getByRole('button', { name: 'Сменить статус' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/bulk', {
        method: 'POST',
        body: { ids: ['page-2'], action: 'status', status: 'review' },
      })
    })
  })

  it('duplicates a page from the row menu', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')
    await screen.findAllByTestId('page-row')

    openRowMenu('Забор из профнастила')
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Дублировать' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/duplicate', { method: 'POST', body: {} })
    })
    expect(await screen.findByText('Копия создана')).toBeTruthy()
  })

  it('unpublishes a published page from the row menu', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')
    await screen.findAllByTestId('page-row')

    openRowMenu('Забор из профнастила')
    expect(await screen.findByRole('menuitem', { name: 'Открыть на сайте' })).toBeTruthy()
    fireEvent.click(screen.getByRole('menuitem', { name: 'Снять с публикации' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/bulk', {
        method: 'POST',
        body: { ids: ['page-1'], action: 'status', status: 'unpublished' },
      })
    })
    expect(await screen.findByText('Страница снята с публикации')).toBeTruthy()
  })

  it('offers preview instead of the live page for drafts', async () => {
    renderWithProviders(<PagesPage />, '/admin/pages')
    await screen.findAllByTestId('page-row')

    openRowMenu('Ворота')
    expect(await screen.findByRole('menuitem', { name: 'Предпросмотр' })).toBeTruthy()
    expect(screen.queryByRole('menuitem', { name: 'Открыть на сайте' })).toBeNull()
    expect(screen.queryByRole('menuitem', { name: 'Снять с публикации' })).toBeNull()
  })

  it('hides duplication and bulk actions from a manager', async () => {
    loginAs('ROLE_MANAGER')
    renderWithProviders(<PagesPage />, '/admin/pages')

    await screen.findAllByTestId('page-row')
    expect(screen.queryByRole('checkbox', { name: 'Выбрать все страницы' })).toBeNull()
    expect(screen.queryByRole('link', { name: 'Создать страницу' })).toBeNull()
    openRowMenu('Забор из профнастила')
    await screen.findByRole('menuitem', { name: 'Редактировать' })
    expect(screen.queryByRole('menuitem', { name: 'Дублировать' })).toBeNull()
  })

  it('lets an SEO specialist select pages but not duplicate them', async () => {
    loginAs('ROLE_SEO')
    renderWithProviders(<PagesPage />, '/admin/pages')

    await screen.findAllByTestId('page-row')
    expect(screen.getByRole('checkbox', { name: 'Выбрать все страницы' })).toBeTruthy()
    openRowMenu('Ворота')
    await screen.findByRole('menuitem', { name: 'Редактировать' })
    expect(screen.queryByRole('menuitem', { name: 'Дублировать' })).toBeNull()
  })
})

describe('PagesPage: новая страница', () => {
  function renderCreate() {
    renderWithProviders(
      <Routes>
        <Route path="/admin/pages/new" element={<PagesPage creating />} />
        <Route path="/admin/pages/:id" element={<p>Редактор открыт</p>} />
        <Route path="/admin/pages" element={<p>Список</p>} />
      </Routes>,
      '/admin/pages/new',
    )
  }

  it('builds the address from the title and creates the page from the chosen template card', async () => {
    loginAs('ROLE_ADMIN')
    renderCreate()

    const dialog = await screen.findByRole('dialog', { name: 'Новая страница' })
    await within(dialog).findByRole('radio', { name: /Страница услуги/ })
    const cards = within(within(dialog).getByRole('radiogroup', { name: 'Шаблон' })).getAllByRole('radio')
    expect(cards.map((card) => card.textContent)).toEqual([
      expect.stringContaining('Страница услуги'),
      expect.stringContaining('Контакты'),
      expect.stringContaining('Пустая страница'),
    ])
    expect(cards[0]?.getAttribute('aria-checked')).toBe('true')
    expect(within(dialog).getByText(/Первый экран · Цены/)).toBeTruthy()

    fireEvent.change(within(dialog).getByLabelText('Название страницы'), { target: { value: 'Забор из сетки' } })
    expect(within(dialog).getByText('/zabor-iz-setki/')).toBeTruthy()

    fireEvent.click(cards[1] as HTMLElement)
    expect(cards[1]?.getAttribute('aria-checked')).toBe('true')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Создать и открыть' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages', {
        method: 'POST',
        body: expect.objectContaining({ title: 'Забор из сетки', h1: 'Забор из сетки', slug: 'zabor-iz-setki', path: '/zabor-iz-setki/', type: 'contacts', template: 'contacts', starterTemplate: 'contacts', parentId: null }),
      })
    })
    expect(await screen.findByText('Редактор открыт')).toBeTruthy()
  })

  it('asks for the page type when the page starts empty', async () => {
    loginAs('ROLE_ADMIN')
    renderCreate()

    const dialog = await screen.findByRole('dialog', { name: 'Новая страница' })
    fireEvent.click(within(dialog).getByRole('radio', { name: /Пустая страница/ }))
    fireEvent.change(within(dialog).getByLabelText('Тип страницы'), { target: { value: 'text_page' } })
    fireEvent.change(within(dialog).getByLabelText('Название страницы'), { target: { value: 'Политика' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Создать и открыть' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages', {
        method: 'POST',
        body: expect.objectContaining({ type: 'text_page', template: 'default', starterTemplate: undefined }),
      })
    })
  })

  it('opens the address fields when the title gives no address', async () => {
    loginAs('ROLE_ADMIN')
    renderCreate()

    const dialog = await screen.findByRole('dialog', { name: 'Новая страница' })
    fireEvent.change(within(dialog).getByLabelText('Название страницы'), { target: { value: '!!' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Создать и открыть' }))

    expect(await within(dialog).findByLabelText('Slug')).toBeTruthy()
    expect(within(dialog).getByText('Укажите slug')).toBeTruthy()
    expect(apiRequest).not.toHaveBeenCalledWith('/admin/api/content/pages', expect.objectContaining({ method: 'POST' }))
  })

  it('returns to the list on close', async () => {
    loginAs('ROLE_ADMIN')
    renderCreate()

    fireEvent.click(within(await screen.findByRole('dialog', { name: 'Новая страница' })).getByRole('button', { name: 'Закрыть' }))
    expect(await screen.findByText('Список')).toBeTruthy()
  })
})
