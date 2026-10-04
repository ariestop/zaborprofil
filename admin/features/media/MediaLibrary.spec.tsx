import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import type { ReactNode } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ToastProvider } from '../../app/providers/toast-provider'
import { ApiError } from '../../shared/api/client'
import type { MediaAssetItem } from '../../types/api'
import { MediaLibrary } from './MediaLibrary'
import { MediaPicker } from './MediaPicker'

const apiRequest = vi.fn()
const apiUpload = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()
  return {
    ...original,
    apiRequest: (...args: unknown[]) => apiRequest(...args),
    apiUpload: (...args: unknown[]) => apiUpload(...args),
  }
})

function makeAsset(id: string, overrides: Partial<MediaAssetItem> = {}): MediaAssetItem {
  return {
    id,
    originalName: `${id}.jpg`,
    filename: `${id}.jpg`,
    publicPath: `/uploads/media/${id}.jpg`,
    mimeType: 'image/jpeg',
    size: 2048,
    width: 800,
    height: 600,
    variants: [],
    alt: null,
    title: null,
    description: null,
    folder: null,
    fileHash: null,
    createdAt: '2026-01-01T00:00:00+00:00',
    ...overrides,
  }
}

function listResponse(assets: MediaAssetItem[], pagination: Partial<{ page: number; perPage: number; total: number; totalPages: number }> = {}) {
  return {
    assets,
    pagination: { page: 1, perPage: 24, total: assets.length, totalPages: 1, ...pagination },
  }
}

function renderWithProviders(node: ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ToastProvider>{node}</ToastProvider>
    </QueryClientProvider>,
  )
}

function lastListPath(): string {
  const calls = apiRequest.mock.calls.filter(([path]) => typeof path === 'string' && path.startsWith('/admin/api/media/assets?'))
  return String(calls[calls.length - 1]?.[0])
}

function mockApi(handlers: {
  list?: (path: string) => unknown
  usages?: (path: string) => unknown
  folders?: Array<{ name: string; count: number }>
  mutate?: (path: string, options: { method?: string; body?: unknown }) => unknown
}): void {
  apiRequest.mockImplementation(async (path: string, options?: { method?: string; body?: unknown }) => {
    if (options?.method !== undefined && options.method !== 'GET') {
      return handlers.mutate?.(path, options) ?? null
    }
    if (path.startsWith('/admin/api/media/folders')) return { folders: handlers.folders ?? [] }
    if (/\/assets\/[^/?]+\/usages/.test(path)) return handlers.usages?.(path) ?? { total: 0, usages: [] }
    return handlers.list?.(path) ?? listResponse([])
  })
}

beforeEach(() => {
  apiRequest.mockReset()
  apiUpload.mockReset()
})

afterEach(cleanup)

describe('MediaLibrary', () => {
  it('renders the asset grid and shows details with alt/title on click', async () => {
    apiRequest.mockResolvedValue(listResponse([makeAsset('fence', { alt: 'Забор' })]))
    renderWithProviders(<MediaLibrary mode="manage" />)

    const grid = await screen.findByTestId('media-grid')
    fireEvent.click(within(grid).getByRole('button', { name: /fence\.jpg/ }))

    const details = await screen.findByTestId('asset-details')
    expect((within(details).getByLabelText(/Alt/) as HTMLInputElement).value).toBe('Забор')
    expect(within(details).queryByRole('button', { name: 'Выбрать' })).toBeNull()
  })

  it('searches with debounce and resets the page', async () => {
    apiRequest.mockResolvedValue(listResponse([makeAsset('a')], { total: 50, totalPages: 3 }))
    renderWithProviders(<MediaLibrary mode="manage" />)
    await screen.findByTestId('media-grid')

    fireEvent.click(screen.getByRole('button', { name: 'Вперёд' }))
    await waitFor(() => expect(lastListPath()).toContain('page=2'))

    fireEvent.change(screen.getByLabelText('Поиск по медиатеке'), { target: { value: 'забор' } })
    await waitFor(() => expect(lastListPath()).toContain('q=%D0%B7%D0%B0%D0%B1%D0%BE%D1%80'))
    expect(lastListPath()).toContain('page=1')
  })

  it('paginates through the server pagination', async () => {
    apiRequest.mockResolvedValue(listResponse([makeAsset('a')], { total: 50, totalPages: 3 }))
    renderWithProviders(<MediaLibrary mode="manage" />)

    expect(await screen.findByTestId('media-page-indicator')).toHaveProperty('textContent', 'Страница 1 из 3')
    expect((screen.getByRole('button', { name: 'Назад' }) as HTMLButtonElement).disabled).toBe(true)
    fireEvent.click(screen.getByRole('button', { name: 'Вперёд' }))
    await waitFor(() => expect(lastListPath()).toContain('page=2'))
  })

  it('uploads files with progress, per-file errors and selects the uploaded asset', async () => {
    const uploaded = makeAsset('new')
    apiRequest.mockResolvedValue(listResponse([]))
    apiUpload.mockImplementation(async (_path: string, body: FormData, options: { onProgress?: (value: number) => void }) => {
      const file = body.get('file') as File
      options.onProgress?.(0.5)
      if (file.name === 'broken.png') {
        throw new ApiError('Uploaded file MIME type is not allowed.', 422, { error: 'x', code: 'VALIDATION' })
      }
      return uploaded
    })
    renderWithProviders(<MediaLibrary mode="manage" />)
    await screen.findByText('Медиатека пуста')

    const input = screen.getByTestId('media-file-input')
    fireEvent.change(input, {
      target: {
        files: [
          new File(['x'], 'new.png', { type: 'image/png' }),
          new File(['x'], 'broken.png', { type: 'image/png' }),
          new File(['x'], 'notes.txt', { type: 'text/plain' }),
        ],
      },
    })

    await waitFor(() => expect(screen.getAllByTestId('upload-item')).toHaveLength(3))
    await waitFor(() => expect(screen.getByText('Загружен')).toBeTruthy())
    expect(screen.getAllByRole('alert').map((node) => node.textContent)).toEqual(
      expect.arrayContaining([expect.stringContaining('Содержимое файла'), expect.stringContaining('Недопустимый тип файла')]),
    )
    expect(apiUpload).toHaveBeenCalledTimes(2)
    expect(await screen.findByTestId('asset-details')).toBeTruthy()
  })

  it('accepts dropped files', async () => {
    apiRequest.mockResolvedValue(listResponse([]))
    apiUpload.mockResolvedValue(makeAsset('dropped'))
    renderWithProviders(<MediaLibrary mode="manage" />)
    await screen.findByText('Медиатека пуста')

    const file = new File(['x'], 'dropped.png', { type: 'image/png' })
    fireEvent.drop(screen.getByTestId('media-library'), { dataTransfer: { files: [file], types: ['Files'] } })

    await waitFor(() => expect(apiUpload).toHaveBeenCalledTimes(1))
  })

  it('deletes an unused file after a plain confirmation without force', async () => {
    mockApi({ list: () => listResponse([makeAsset('gone', { usageCount: 0 })]) })
    renderWithProviders(<MediaLibrary mode="manage" />)

    fireEvent.click(await screen.findByRole('button', { name: /gone\.jpg/ }))
    const details = await screen.findByTestId('asset-details')
    expect(await within(details).findByText('Нигде не используется.')).toBeTruthy()
    fireEvent.click(within(details).getByRole('button', { name: 'Удалить' }))

    const dialog = await screen.findByRole('dialog')
    expect(apiRequest).not.toHaveBeenCalledWith('/admin/api/media/assets/gone', expect.anything())
    expect(await within(dialog).findByText(/нигде не используется/)).toBeTruthy()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Удалить' }))

    await waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/admin/api/media/assets/gone', { method: 'DELETE' }))
  })

  it('blocks deleting a used file until the user confirms, then sends force', async () => {
    mockApi({
      list: () => listResponse([makeAsset('hero', { usageCount: 2 })]),
      usages: () => ({
        total: 2,
        usages: [
          { type: 'page_block', sourceId: 'B1', title: 'Заборы из профнастила', location: 'Блок «Hero» (hero)', adminPath: '/admin/pages/P1/builder', status: 'published' },
          { type: 'page_seo', sourceId: 'P2', title: 'Ворота', location: 'OG-изображение', adminPath: '/admin/pages/P2', status: 'draft' },
        ],
      }),
    })
    renderWithProviders(<MediaLibrary mode="manage" />)

    fireEvent.click(await screen.findByRole('button', { name: /hero\.jpg/ }))
    const details = await screen.findByTestId('asset-details')
    fireEvent.click(within(details).getByRole('button', { name: 'Удалить' }))

    const dialog = await screen.findByRole('dialog')
    expect(await within(dialog).findByText('Файл используется в 2 местах. После удаления изображения на этих страницах перестанут отображаться.')).toBeTruthy()
    const links = within(dialog).getAllByRole('link')
    expect(links.map((link) => link.getAttribute('href'))).toEqual(['/admin/pages/P1/builder', '/admin/pages/P2'])

    const confirm = within(dialog).getByRole('button', { name: 'Удалить всё равно' }) as HTMLButtonElement
    expect(confirm.disabled).toBe(true)
    fireEvent.click(within(dialog).getByRole('checkbox'))
    expect(confirm.disabled).toBe(false)
    fireEvent.click(confirm)

    await waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/admin/api/media/assets/hero?force=1', { method: 'DELETE' }))
  })

  it('saves alt, title, description and folder through PATCH', async () => {
    mockApi({
      list: () => listResponse([makeAsset('a')]),
      folders: [{ name: 'Заборы', count: 3 }],
      mutate: () => makeAsset('a', { alt: 'Новый alt', title: 'T' }),
    })
    renderWithProviders(<MediaLibrary mode="manage" />)

    fireEvent.click(await screen.findByRole('button', { name: /a\.jpg/ }))
    const details = await screen.findByTestId('asset-details')
    fireEvent.change(within(details).getByLabelText(/Alt/), { target: { value: 'Новый alt' } })
    fireEvent.change(within(details).getByLabelText(/Title/), { target: { value: 'T' } })
    fireEvent.change(within(details).getByLabelText(/Описание/), { target: { value: 'Объект на Ленина' } })
    fireEvent.change(within(details).getByLabelText(/Папка/), { target: { value: ' Заборы ' } })
    fireEvent.click(within(details).getByRole('button', { name: 'Сохранить' }))

    await waitFor(() =>
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/media/assets/a', {
        method: 'PATCH',
        body: { alt: 'Новый alt', title: 'T', description: 'Объект на Ленина', folder: 'Заборы' },
      }),
    )
  })

  it('sends extended filters, resets them and changes the page size', async () => {
    mockApi({ list: () => listResponse([makeAsset('a')], { total: 60, totalPages: 3 }), folders: [{ name: 'Заборы', count: 3 }] })
    renderWithProviders(<MediaLibrary mode="manage" />)
    await screen.findByTestId('media-grid')

    fireEvent.change(screen.getByLabelText('Формат'), { target: { value: 'webp' } })
    await waitFor(() => expect(lastListPath()).toContain('format=webp'))
    fireEvent.change(screen.getByLabelText('Использование'), { target: { value: 'unused' } })
    await waitFor(() => expect(lastListPath()).toContain('usage=unused'))
    await screen.findByRole('option', { name: 'Заборы (3)' })
    fireEvent.change(screen.getByLabelText('Папка'), { target: { value: 'Заборы' } })
    await waitFor(() => expect(lastListPath()).toContain('folder=%D0%97%D0%B0%D0%B1%D0%BE%D1%80%D1%8B'))
    fireEvent.change(screen.getByLabelText('Загружены с'), { target: { value: '2026-01-01' } })
    fireEvent.change(screen.getByLabelText('Загружены по'), { target: { value: '2026-02-01' } })
    await waitFor(() => expect(lastListPath()).toContain('to=2026-02-01'))
    expect(lastListPath()).toContain('from=2026-01-01')

    fireEvent.change(screen.getByLabelText('Файлов на странице'), { target: { value: '48' } })
    await waitFor(() => expect(lastListPath()).toContain('perPage=48'))
    expect(screen.getByTestId('media-range').textContent).toContain('из 60')

    fireEvent.click(screen.getByRole('button', { name: 'В конец' }))
    await waitFor(() => expect(lastListPath()).toContain('page=3'))

    fireEvent.click(screen.getByRole('button', { name: 'Сбросить фильтры' }))
    await waitFor(() => expect(lastListPath()).not.toContain('format='))
    expect(lastListPath()).not.toContain('usage=')
    expect(lastListPath()).not.toContain('folder=')
    expect(lastListPath()).not.toContain('from=')
    expect(lastListPath()).toContain('page=1')
  })

  it('shows usage badges in the grid', async () => {
    mockApi({ list: () => listResponse([makeAsset('used', { usageCount: 3 }), makeAsset('free', { usageCount: 0 })]) })
    renderWithProviders(<MediaLibrary mode="manage" />)

    const grid = await screen.findByTestId('media-grid')
    expect(within(grid).getByText('Используется · 3')).toBeTruthy()
    expect(within(grid).getByText('Не используется')).toBeTruthy()
  })

  it('tells the user when an upload matched an existing file', async () => {
    mockApi({ list: () => listResponse([]) })
    apiUpload.mockResolvedValue(makeAsset('same', { duplicate: true }))
    renderWithProviders(<MediaLibrary mode="manage" />)
    await screen.findByText('Медиатека пуста')

    fireEvent.change(screen.getByTestId('media-file-input'), { target: { files: [new File(['x'], 'same.png', { type: 'image/png' })] } })

    expect((await screen.findByTestId('duplicate-note')).textContent).toContain('уже есть в медиатеке')
    expect(screen.getByText('Уже в медиатеке')).toBeTruthy()
  })

  it('shows an error state when the list fails', async () => {
    apiRequest.mockRejectedValue(new ApiError('Internal server error', 500, { error: 'Internal server error', code: 'INTERNAL' }))
    renderWithProviders(<MediaLibrary mode="manage" />)

    expect(await screen.findByText('Не удалось загрузить медиатеку')).toBeTruthy()
  })
})

describe('MediaPicker', () => {
  it('selects an asset from the library and reports the public path with the asset', async () => {
    const onChange = vi.fn()
    apiRequest.mockResolvedValue(listResponse([makeAsset('hero', { alt: 'Hero alt' })]))
    renderWithProviders(<MediaPicker label="Изображение" value="" onChange={onChange} />)

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(await within(dialog).findByRole('button', { name: /hero\.jpg/ }))
    fireEvent.click(await within(dialog).findByRole('button', { name: 'Выбрать' }))

    expect(onChange).toHaveBeenCalledWith('/uploads/media/hero.jpg', expect.objectContaining({ id: 'hero' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull())
  })

  it('can produce absolute URLs and clear the value', async () => {
    const onChange = vi.fn()
    apiRequest.mockResolvedValue(listResponse([makeAsset('og')]))
    renderWithProviders(<MediaPicker label="OG" value="https://x.test/a.jpg" absoluteUrl onChange={onChange} />)

    fireEvent.click(screen.getByRole('button', { name: 'Очистить' }))
    expect(onChange).toHaveBeenCalledWith('')

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.doubleClick(await within(dialog).findByRole('button', { name: /og\.jpg/ }))

    expect(onChange).toHaveBeenLastCalledWith(`${window.location.origin}/uploads/media/og.jpg`, expect.objectContaining({ id: 'og' }))
  })

  it('lets the user upload straight from the picker and choose the new file', async () => {
    const onChange = vi.fn()
    const uploaded = makeAsset('fresh')
    apiRequest.mockResolvedValue(listResponse([]))
    apiUpload.mockResolvedValue(uploaded)
    renderWithProviders(<MediaPicker label="Изображение" value="" onChange={onChange} />)

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.change(within(dialog).getByTestId('media-file-input'), { target: { files: [new File(['x'], 'fresh.png', { type: 'image/png' })] } })

    fireEvent.click(await within(dialog).findByRole('button', { name: 'Выбрать' }))
    expect(onChange).toHaveBeenCalledWith('/uploads/media/fresh.jpg', expect.objectContaining({ id: 'fresh' }))
  })
})
