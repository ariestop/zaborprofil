import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { createMemoryRouter, Link, RouterProvider, useParams } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ToastProvider } from '../../app/providers/toast-provider'
import { useBuilderStore } from '../../modules/page-builder/state/builderStore'
import { makePage } from './fixtures'
import { PageEditor } from './PageEditor'
import type { EditorTab } from './form'
import { resolveEditorTab } from './editor-tab'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
}

function EditorRoute() {
  const { tab } = useParams()
  const resolved: EditorTab = resolveEditorTab(tab) ?? 'content'

  return (
    <>
      <PageEditor page={makePage()} initialBlocks={[]} tab={resolved} />
      <Link to="/admin/other">outside-link</Link>
    </>
  )
}

function renderEditor(initialEntry: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  const router = createMemoryRouter(
    [
      { path: '/admin/pages/:id/:tab?', element: <EditorRoute /> },
      { path: '/admin/pages', element: <p>pages-list</p> },
      { path: '/admin/other', element: <p>other-screen</p> },
    ],
    { initialEntries: [initialEntry] },
  )

  render(
    <QueryClientProvider client={client}>
      <ToastProvider>
        <RouterProvider router={router} />
      </ToastProvider>
    </QueryClientProvider>,
  )

  return router
}

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string) => {
    if (url.endsWith('/admin/api/content/pages')) {
      return Promise.resolve({ pages: [] })
    }

    if (url.endsWith('/templates')) {
      return Promise.resolve({ templates: [] })
    }

    return Promise.resolve([])
  })
  useBuilderStore.getState().setBlocks([])
})

afterEach(cleanup)

describe('PageEditor', () => {
  it('renders four tabs with the active one selected and quick actions in the header', async () => {
    renderEditor('/admin/pages/page-1/seo')

    const tabs = await screen.findAllByRole('tab')
    expect(tabs.map((tab) => tab.textContent)).toEqual(['Контент и блоки', 'SEO', 'Настройки', 'Ревизии'])
    expect(screen.getByRole('tab', { name: 'SEO' }).getAttribute('aria-selected')).toBe('true')
    expect(screen.getByRole('button', { name: 'Предпросмотр' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Опубликовать' })).toBeTruthy()
    expect(screen.getByTestId('save-indicator').dataset.state).toBe('clean')
  })

  it('keeps unsaved SEO edits when switching tabs and does not warn about in-page navigation', async () => {
    renderEditor('/admin/pages/page-1/seo')

    const input = await screen.findByLabelText('SEO-заголовок (title)')
    fireEvent.change(input, { target: { value: 'Мой SEO-заголовок' } })
    await waitFor(() => expect(screen.getByTestId('save-indicator').dataset.state).toBe('dirty'))

    fireEvent.click(screen.getByRole('tab', { name: /Настройки/ }))
    expect(await screen.findByLabelText('Slug')).toBeTruthy()
    expect(screen.queryByText('Есть несохранённые изменения', { selector: '[role="dialog"] *' })).toBeNull()

    fireEvent.click(screen.getByRole('tab', { name: /SEO/ }))
    expect((await screen.findByLabelText('SEO-заголовок (title)') as HTMLInputElement).value).toBe('Мой SEO-заголовок')
  })

  it('asks for confirmation when leaving the page with unsaved changes', async () => {
    const router = renderEditor('/admin/pages/page-1/seo')

    fireEvent.change(await screen.findByLabelText('SEO-заголовок (title)'), { target: { value: 'Правка' } })
    await waitFor(() => expect(screen.getByTestId('save-indicator').dataset.state).toBe('dirty'))

    fireEvent.click(screen.getByText('outside-link'))
    expect(await screen.findByRole('dialog', { name: 'Есть несохранённые изменения' })).toBeTruthy()
    expect(router.state.location.pathname).toBe('/admin/pages/page-1/seo')

    fireEvent.click(screen.getByText('Уйти без сохранения'))
    await waitFor(() => expect(router.state.location.pathname).toBe('/admin/other'))
  })

  it('leaves without a prompt when nothing changed', async () => {
    const router = renderEditor('/admin/pages/page-1/settings')

    await screen.findByLabelText('Slug')
    fireEvent.click(screen.getByText('outside-link'))

    await waitFor(() => expect(router.state.location.pathname).toBe('/admin/other'))
  })
})
